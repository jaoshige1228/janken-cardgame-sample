resource "local_file" "app_env" {
  filename = "${path.module}/generated/app.env"
  content = templatefile("${path.module}/templates/app.env.tpl", {
    app_key                 = var.app_key
    app_url                 = "https://${aws_cloudfront_distribution.main.domain_name}"
    db_host                 = aws_lightsail_database.mysql.master_endpoint_address
    db_password             = var.db_password
    reverb_app_id           = var.reverb_app_id
    reverb_app_key          = var.reverb_app_key
    reverb_app_secret       = var.reverb_app_secret
    reverb_host             = aws_cloudfront_distribution.main.domain_name
    reverb_broadcasting_host = "127.0.0.1"
  })

  depends_on = [aws_cloudfront_distribution.main]
}
