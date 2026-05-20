<?php

namespace Tests\Feature;

use App\Models\Game;
use App\Models\Player;
use App\Services\GameStateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GameFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_match_without_draw(): void
    {
        $svc = app(GameStateService::class);
        $p1 = $svc->createRoom();
        $p2 = $svc->joinRoom($p1['room_code']);

        $game = Game::findOrFail($p1['game_id']);
        $player1 = Player::where('token', $p1['player_token'])->firstOrFail();
        $player2 = Player::where('token', $p2['player_token'])->firstOrFail();

        $svc->playCard($player1, $game, 'rock');
        $game->refresh();
        $svc->endTurn($player1, $game);
        $game->refresh();

        $svc->playCard($player2, $game, 'scissors');
        $game->refresh();
        $svc->endTurn($player2, $game);
        $game->refresh();

        $this->assertSame(GameStateService::PHASE_FINISHED, $game->phase);
        $this->assertSame('player1', $game->winner);
    }

    public function test_draw_resets_to_first_turn(): void
    {
        $svc = app(GameStateService::class);
        $p1 = $svc->createRoom();
        $p2 = $svc->joinRoom($p1['room_code']);

        $game = Game::findOrFail($p1['game_id']);
        $player1 = Player::where('token', $p1['player_token'])->firstOrFail();
        $player2 = Player::where('token', $p2['player_token'])->firstOrFail();

        $svc->playCard($player1, $game, 'rock');
        $game->refresh();
        $svc->endTurn($player1, $game);
        $game->refresh();

        $svc->playCard($player2, $game, 'rock');
        $game->refresh();
        $svc->endTurn($player2, $game);
        $game->refresh();

        $this->assertSame(GameStateService::PHASE_FIRST, $game->phase);
        $this->assertSame(1, $game->current_turn_slot);
        $this->assertNull($game->winner);
    }
}
