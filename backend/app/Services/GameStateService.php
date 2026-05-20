<?php

namespace App\Services;

use App\Events\BattleResult;
use App\Events\CardPlayed;
use App\Events\GameEnded;
use App\Events\GameStarted;
use App\Events\PlayerJoined;
use App\Events\TurnChanged;
use App\Models\Game;
use App\Models\Player;
use App\Models\Room;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class GameStateService
{
    public const PHASE_WAITING = 'waiting_players';

    public const PHASE_FIRST = 'first_turn';

    public const PHASE_SECOND = 'second_turn';

    public const PHASE_BATTLE = 'battle';

    public const PHASE_FINISHED = 'finished';

    public static function freshHand(): array
    {
        return [
            'rock' => true,
            'paper' => true,
            'scissors' => true,
        ];
    }

    public function createRoom(): array
    {
        return DB::transaction(function () {
            $code = $this->generateUniqueRoomCode();
            $room = Room::create([
                'code' => $code,
                'status' => 'waiting',
            ]);
            $token = Str::random(48);
            Player::create([
                'room_id' => $room->id,
                'slot' => 1,
                'token' => $token,
            ]);
            $game = Game::create([
                'room_id' => $room->id,
                'phase' => self::PHASE_WAITING,
                'current_turn_slot' => null,
                'hand1' => null,
                'hand2' => null,
            ]);

            return [
                'room_code' => $room->code,
                'player_token' => $token,
                'slot' => 1,
                'game_id' => $game->id,
                'room_id' => $room->id,
            ];
        });
    }

    public function joinRoom(string $code): array
    {
        return DB::transaction(function () use ($code) {
            $room = Room::where('code', $code)->lockForUpdate()->firstOrFail();
            if ($room->status !== 'waiting') {
                throw new InvalidArgumentException('ROOM_NOT_JOINABLE');
            }
            if ($room->players()->count() >= 2) {
                throw new InvalidArgumentException('ROOM_FULL');
            }
            $token = Str::random(48);
            $player = Player::create([
                'room_id' => $room->id,
                'slot' => 2,
                'token' => $token,
            ]);
            $room->update(['status' => 'playing']);

            $game = Game::where('room_id', $room->id)->lockForUpdate()->firstOrFail();
            $game->update([
                'phase' => self::PHASE_FIRST,
                'current_turn_slot' => 1,
                'hand1' => self::freshHand(),
                'hand2' => self::freshHand(),
                'card1' => null,
                'card2' => null,
                'winner' => null,
            ]);
            $game->refresh();

            broadcast(new PlayerJoined($room->id, 2))->toOthers();
            broadcast(new GameStarted($room->id, $game->id));

            return [
                'room_code' => $room->code,
                'player_token' => $token,
                'slot' => 2,
                'game_id' => $game->id,
                'room_id' => $room->id,
            ];
        });
    }

    public function playCard(Player $player, Game $game, string $card): Game
    {
        if (! in_array($card, ['rock', 'paper', 'scissors'], true)) {
            throw new InvalidArgumentException('INVALID_CARD');
        }

        return DB::transaction(function () use ($player, $game, $card) {
            $game = Game::where('id', $game->id)->lockForUpdate()->firstOrFail();
            if ($game->room_id !== $player->room_id) {
                throw new InvalidArgumentException('FORBIDDEN');
            }
            $slot = $player->slot;
            if ($game->current_turn_slot !== $slot) {
                throw new InvalidArgumentException('NOT_YOUR_TURN');
            }
            if ($game->phase !== self::PHASE_FIRST && $game->phase !== self::PHASE_SECOND) {
                throw new InvalidArgumentException('INVALID_PHASE');
            }
            if (($game->phase === self::PHASE_FIRST && $slot !== 1) || ($game->phase === self::PHASE_SECOND && $slot !== 2)) {
                throw new InvalidArgumentException('INVALID_PHASE');
            }

            $handKey = $slot === 1 ? 'hand1' : 'hand2';
            $cardKey = $slot === 1 ? 'card1' : 'card2';
            $hand = $game->{$handKey} ?? [];
            if (empty($hand[$card])) {
                throw new InvalidArgumentException('CARD_UNAVAILABLE');
            }
            if ($game->{$cardKey} !== null) {
                throw new InvalidArgumentException('ALREADY_PLAYED');
            }

            $hand[$card] = false;
            $game->{$handKey} = $hand;
            $game->{$cardKey} = $card;
            $game->save();

            broadcast(new CardPlayed($game->room_id, $slot));

            return $game->fresh();
        });
    }

    public function endTurn(Player $player, Game $game): Game
    {
        return DB::transaction(function () use ($player, $game) {
            $game = Game::where('id', $game->id)->lockForUpdate()->firstOrFail();
            if ($game->room_id !== $player->room_id) {
                throw new InvalidArgumentException('FORBIDDEN');
            }
            $slot = $player->slot;
            if ($game->current_turn_slot !== $slot) {
                throw new InvalidArgumentException('NOT_YOUR_TURN');
            }

            if ($game->phase === self::PHASE_FIRST) {
                if ($game->card1 === null) {
                    throw new InvalidArgumentException('PLAY_CARD_FIRST');
                }
                $game->phase = self::PHASE_SECOND;
                $game->current_turn_slot = 2;
                $game->save();
                broadcast(new TurnChanged($game->room_id, $game->phase, $game->current_turn_slot));

                return $game->fresh();
            }

            if ($game->phase === self::PHASE_SECOND) {
                if ($game->card2 === null) {
                    throw new InvalidArgumentException('PLAY_CARD_FIRST');
                }
                $game->phase = self::PHASE_BATTLE;
                $game->current_turn_slot = null;
                $game->save();

                $outcome = $this->resolveWinner($game->card1, $game->card2);
                broadcast(new BattleResult($game->room_id, $game->card1, $game->card2, $outcome));

                if ($outcome === 'draw') {
                    $game->phase = self::PHASE_FIRST;
                    $game->current_turn_slot = 1;
                    $game->hand1 = self::freshHand();
                    $game->hand2 = self::freshHand();
                    $game->card1 = null;
                    $game->card2 = null;
                    $game->winner = null;
                    $game->save();
                    broadcast(new TurnChanged($game->room_id, $game->phase, $game->current_turn_slot));
                } else {
                    $game->winner = $outcome;
                    $game->phase = self::PHASE_FINISHED;
                    $game->save();
                    Room::where('id', $game->room_id)->update(['status' => 'done']);
                    broadcast(new GameEnded($game->room_id, $outcome));
                }

                return $game->fresh();
            }

            throw new InvalidArgumentException('INVALID_PHASE');
        });
    }

    public function buildClientState(Game $game, Player $player): array
    {
        $roomId = $game->room_id;
        $slot = $player->slot;

        $showCard = function (?string $card, int $ownerSlot) use ($slot, $game) {
            if ($card === null) {
                return null;
            }
            if ($ownerSlot === $slot) {
                return $card;
            }
            if ($game->phase === self::PHASE_FINISHED || $game->phase === self::PHASE_BATTLE) {
                return $card;
            }

            return 'hidden';
        };

        return [
            'game_id' => $game->id,
            'room_id' => $roomId,
            'phase' => $game->phase,
            'current_turn_slot' => $game->current_turn_slot,
            'your_slot' => $slot,
            'your_hand' => $slot === 1 ? $game->hand1 : $game->hand2,
            'field' => [
                'player1_card' => $showCard($game->card1, 1),
                'player2_card' => $showCard($game->card2, 2),
            ],
            'winner' => $game->winner,
        ];
    }

    private function resolveWinner(string $a, string $b): string
    {
        if ($a === $b) {
            return 'draw';
        }
        $beats = [
            'rock' => 'scissors',
            'scissors' => 'paper',
            'paper' => 'rock',
        ];

        return $beats[$a] === $b ? 'player1' : 'player2';
    }

    private function generateUniqueRoomCode(): string
    {
        do {
            $code = strtoupper(Str::random(6));
        } while (Room::where('code', $code)->exists());

        return $code;
    }
}
