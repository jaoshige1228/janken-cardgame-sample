# じゃんけんカード（1vs1 リアルタイム）

Laravel（API + Reverb）・Vue（SPA）・MySQL 構成の Web アプリです。フロントエンドのビルド・開発には **Node.js v22** を使用してください（`frontend/.nvmrc`・`package.json` の `engines` 参照）。

---

## 前提条件

| 用途 | 必要なもの |
|------|----------------|
| Docker で起動 | Docker Desktop など（Compose v2） |
| フロントをビルド | Node.js **22.x**（`nvm use` または `frontend/.nvmrc`） |
| AWS デプロイ | AWS アカウント、Terraform 1.5+、AWS CLI（認証済み） |

---

## 環境構築（ローカル・Docker）

リポジトリルートで作業します。

### 1. フロントエンドのビルド成果物を用意する

Nginx は `frontend/dist` を配信します。初回およびフロント変更後はビルドが必要です。

**方法 A: ホストに Node 22 がある場合**

```bash
cd frontend
npm ci
npm run build
cd ..
```

**方法 B: Node 22 コンテナでビルドする（Docker のみで完結）**

```bash
docker compose --profile build run --rm frontend-build
```

### 2. コンテナ起動

```bash
docker compose up -d --build
```

**リアルタイムが効かないとき:** 本番ビルドで API を `http://localhost` などに固定すると、`http://127.0.0.1` で開いたタブからは **別オリジン** になり、`/broadcasting/auth` が失敗して Echo のプライベートチャンネルに入れません。フロントは **`/api` と `/broadcasting/auth` を相対パス**で呼ぶようになっています。開発は `npm run dev` のプロキシ（`frontend/.env` の `VITE_API_URL`）を `php artisan serve` のホスト・ポートに合わせてください。

PHP イメージには **pcntl** 拡張を有効化してあり、Reverb がシグナル（`SIGINT` 等）を扱えます。あわせて `laravel/reverb` には名前空間まわりの既知の不整合を避ける **Composer パッチ**（[`backend/patches/laravel-reverb-sigint.patch`](backend/patches/laravel-reverb-sigint.patch)）を当てています。`docker/php/Dockerfile` を変えたあとは `docker compose build --no-cache php reverb` でイメージを作り直してください。

- **アプリ（SPA + API）**: http://localhost  
- **Reverb（WebSocket）**: ホストの `8080`（ブラウザから `ws://localhost:8080` で接続。フロントの `frontend/.env` の `VITE_*` がこれに合っていること）

### 3. 環境変数（Docker）

バックエンド・Reverb 用の変数は [`docker/laravel.env`](docker/laravel.env) です。本番相当に変える場合は `APP_DEBUG`、`DB_*`、`REVERB_*` などを編集し、**フロントの `frontend/.env` も Reverb 公開鍵・ホスト・ポート・スキームを一致**させてから `npm run build` をやり直してください。

**Laravel と Reverb が別コンテナのとき:** ブラウザはホストの `localhost:8080` に WebSocket 接続しますが、**PHP からのブロードキャスト送信**は Reverb へ HTTP します。コンテナ内の `localhost` は自分自身を指すため、[`REVERB_BROADCASTING_HOST=reverb`](docker/laravel.env)（Compose のサービス名）を [`config/broadcasting.php`](backend/config/broadcasting.php) で参照する必要があります。`REVERB_HOST=localhost` は `config/reverb.php` 側の公開名用に残し、混同しないでください。

### 4. よくある操作

| 操作 | コマンド例 |
|------|------------|
| ログ確認 | `docker compose logs -f php` または `reverb` |
| 停止 | `docker compose down` |
| DB ごと消す | `docker compose down -v` |

---


## デプロイ手順（AWS・Terraform）

本番構成は **Vue → S3 + CloudFront**、**Laravel + Reverb → Lightsail インスタンス（Docker）**、**MySQL → Lightsail Managed Database** です。定義は [`infra/terraform/`](infra/terraform/) にあります。

| コンポーネント | AWS リソース |
|----------------|--------------|
| フロント（Vue） | S3 + CloudFront（デフォルトドメイン） |
| API / Reverb | Lightsail 静的 IP + Docker（[`docker/prod/Dockerfile.backend`](docker/prod/Dockerfile.backend)） |
| MySQL | Lightsail Managed Database |

CloudFront が `/api`・`/broadcasting`・`/up`・`/app`（WebSocket）を Lightsail に振り分け、それ以外を S3 の SPA に渡します。Reverb は Nginx が **80 番で WebSocket を 8080 にプロキシ**するため、ブラウザは `wss://<cloudfront>/app/...` で接続します。

### 0. 旧構成（ECS / RDS）をまだ使っている場合

以前の Terraform で ECS・ALB・ECR・RDS を作っている場合は、**新しい `apply` の前に**旧定義で destroy してください（課金を止めるため）。

```bash
# 旧 ecs.tf 等が残っているディレクトリでのみ実行
cd infra/terraform
export AWS_PROFILE=geno-aws
terraform init
terraform destroy
```

認証エラーになる場合は `aws sts get-caller-identity` が成功するまで SSO の再ログインや `aws configure` を見直してください。

### 1. 変数ファイルの用意

```bash
cd infra/terraform
cp terraform.tfvars.example terraform.tfvars
```

[`terraform.tfvars.example`](infra/terraform/terraform.tfvars.example) をコピーし、次をすべて実値に置き換えます（**コミットしないでください**）。

| 変数 | 内容 |
|------|------|
| `aws_region` | デプロイ先リージョン（例: `ap-northeast-1`） |
| `project_name` | リソース名プレフィックス（例: `janken-card`） |
| `lightsail_availability_zone` | 例: `ap-northeast-1a` |
| `lightsail_instance_bundle_id` | 例: `small_3_0`（Docker ビルド用。最小は `nano_3_0`） |
| `lightsail_db_bundle_id` | 例: `micro_2_0` |
| `db_password` | Lightsail MySQL ユーザー `janken` のパスワード |
| `app_key` | `php artisan key:generate --show` の出力 |
| `reverb_app_*` | [`backend/.env`](backend/.env) と同じ値。`reverb_app_key` は **`VITE_REVERB_APP_KEY` と同一** |

### 2. 初期化と適用

```bash
cd infra/terraform
terraform init
terraform plan
terraform apply
```

`apply` 後、`infra/terraform/generated/app.env` に Laravel 用 `.env` が生成されます（`.gitignore` 済み）。

### 3. Terraform 出力

| 出力 | 用途 |
|------|------|
| `cloudfront_url` | ブラウザで開く本番 URL（`https://....cloudfront.net`） |
| `s3_bucket_name` | フロントの `aws s3 sync` 先 |
| `cloudfront_distribution_id` | キャッシュ無効化 |
| `lightsail_static_ip` | SSH 接続先 |
| `lightsail_instance_name` | Lightsail CLI 用 |
| `database_endpoint` | `DB_HOST` 確認 |
| `app_env_file` | Lightsail にコピーする `.env` のパス |

### 4. フロントエンド（S3 + CloudFront）

Vite は **`VITE_*` をビルド時に埋め込み**ます。CloudFront 経由では次を指定します。

```bash
cd frontend
export VITE_REVERB_APP_KEY='<terraform.tfvars の reverb_app_key>'
export VITE_REVERB_SCHEME=https
export VITE_REVERB_PORT=443
npm ci
npm run build
```

一括デプロイ（[`scripts/deploy-frontend.sh`](scripts/deploy-frontend.sh)）:

```bash
./scripts/deploy-frontend.sh
```

手動の場合:

```bash
cd infra/terraform
BUCKET=$(terraform output -raw s3_bucket_name)
DIST_ID=$(terraform output -raw cloudfront_distribution_id)
aws s3 sync ../../frontend/dist/ "s3://${BUCKET}/" --delete
aws cloudfront create-invalidation --distribution-id "$DIST_ID" --paths "/*"
```

### 5. バックエンド（Lightsail + Docker）

| ファイル | 役割 |
|----------|------|
| [`docker/prod/Dockerfile.backend`](docker/prod/Dockerfile.backend) | Laravel + Nginx + Reverb（フロントは含まない） |
| [`docker/prod/nginx.conf`](docker/prod/nginx.conf) | API を PHP-FPM へ、`/app/` を Reverb(8080) へプロキシ |
| [`docker/prod/supervisord.conf`](docker/prod/supervisord.conf) | php-fpm / nginx / reverb |

#### 5.1 Lightsail に SSH

Lightsail コンソールで SSH キーを取得するか、[`lightsail_static_ip`](infra/terraform/outputs.tf) へ SSH します。初回起動時に user_data で Docker が入ります（数分かかることがあります）。

#### 5.2 リポジトリと .env

```bash
# Lightsail 上
sudo mkdir -p /opt/janken
# ローカルから generated/app.env をコピー（例）
# scp infra/terraform/generated/app.env ec2-user@<lightsail_static_ip>:/tmp/app.env
sudo mv /tmp/app.env /opt/janken/.env
sudo chown ec2-user:ec2-user /opt/janken/.env

git clone <このリポジトリの URL> /opt/janken/src
cd /opt/janken/src
```

#### 5.3 イメージのビルドと起動

```bash
cd /opt/janken/src
docker build -f docker/prod/Dockerfile.backend -t janken-backend .
docker rm -f janken-backend 2>/dev/null || true
docker run -d --name janken-backend --restart unless-stopped \
  -p 80:80 \
  --env-file /opt/janken/.env \
  janken-backend
```

更新時は `git pull` → `docker build` → 上記 `docker run` をやり直してください。

#### 5.4 動作確認

```bash
curl -sS "https://$(terraform -chdir=infra/terraform output -raw cloudfront_domain_name)/up"
```

ブラウザで `terraform output -raw cloudfront_url` を開き、対戦（WebSocket）まで確認します。

### 6. データ移行（旧 RDS から）

旧 RDS を destroy する前に:

```bash
mysqldump -h <旧RDSホスト> -u janken -p janken > backup.sql
```

Lightsail DB 作成後、Lightsail インスタンスから `mysql -h <database_endpoint> -u janken -p janken < backup.sql` でリストアします。

### 7. 初回チェックリスト

1. ローカルで `APP_KEY` / `REVERB_APP_*` を確定
2. `terraform.tfvars` を埋めて `terraform apply`
3. `./scripts/deploy-frontend.sh`（または手動 S3 sync + invalidation）
4. Lightsail で Docker 起動（`.env` は `generated/app.env`）
5. `cloudfront_url` で SPA・API・リアルタイムを確認

### 8. コスト・運用メモ

| 項目 | 内容 |
|------|------|
| 削減 | ECS Fargate + ALB + RDS の固定費を撤去 |
| 残り | Lightsail インスタンス + Managed DB + S3/CloudFront 従量 |
| 可用性 | 単一 Lightsail（冗長なし） |
| シークレット | 現状 Terraform state / `generated/app.env` に平文。本番は Secrets Manager 等を検討 |
| 独自ドメイン | 未対応。必要なら ACM + CloudFront 別設定 |

### 9. よくあるエラー

- **`NoSuchOriginRequestPolicy: The specified origin request policy does not exist`**  
  マネージドポリシー **Managed-AllViewer** の ID が誤っていると出ます。正しい ID は `216adef6-5c7f-47e4-b989-5492eafa07d3` です（[`locals.tf`](infra/terraform/locals.tf) で参照）。修正後に `terraform apply` を再実行してください。
- **`The parameter origin name cannot be an IP address`**  
  CloudFront はオリジンに IP を直接指定できません。本リポジトリでは Lightsail 静的 IP を **`{IPのドットをハイフンにした}.sslip.io`**（例: `54.199.1.2` → `54-199-1-2.sslip.io`）として参照します。`terraform output lightsail_api_origin_domain` で確認できます。
- **`Some names are already in use: janken-card-db`**  
  前回の `apply` が途中失敗すると、AWS 上だけ DB が残り Terraform state に無い状態になります。**削除せず取り込む**場合:
  ```bash
  cd infra/terraform
  terraform import aws_lightsail_database.mysql janken-card-db
  terraform apply
  ```
  作り直す場合は Lightsail コンソールで `janken-card-db` を削除してから `terraform apply` してください。
- **`unexpected state 'backing-up', wanted target 'available'`（import 時）**  
  DB 作成直後や自動バックアップ中は `backing-up` になり、Terraform は import / refresh できません。**`available` になるまで待ってから** import をやり直してください。
  ```bash
  aws lightsail get-relational-database --relational-database-name janken-card-db \
    --query 'relationalDatabase.state' --output text
  # "available" になるまで数分待つ
  terraform import aws_lightsail_database.mysql janken-card-db
  terraform apply
  ```
- **`InvalidClientTokenId` / STS 403**  
  `aws sts get-caller-identity` が成功するまで認証を直してから `terraform apply` してください。
- **リアルタイムだけ失敗**  
  フロントを `VITE_REVERB_SCHEME=https` と `VITE_REVERB_PORT=443` で再ビルドしたか、Nginx の `/app/` プロキシが有効か確認してください。
- **DB 接続失敗**  
  Lightsail DB とインスタンスが同一リージョンか、`generated/app.env` の `DB_HOST` が `database_endpoint` と一致しているか確認してください。

---

## テスト（バックエンド）

```bash
cd backend
php artisan test
```

---

## ディレクトリ構成（抜粋）

```
├── backend/          # Laravel
├── frontend/         # Vue + Vite
├── docker/           # 開発用 Dockerfile・nginx・laravel.env 例
├── docker/prod/      # 本番バックエンド Docker（Lightsail 向け）
├── scripts/          # deploy-frontend.sh など
├── docker-compose.yml
└── infra/terraform/  # AWS リソース定義
```
