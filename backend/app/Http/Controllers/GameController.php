<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\Player;
use App\Services\GameStateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class GameController extends Controller
{
    public function __construct(
        private GameStateService $games,
    ) {}

    public function show(Request $request, Game $game): JsonResponse
    {
        /** @var Player $player */
        $player = $request->attributes->get('player');
        if ($game->room_id !== $player->room_id) {
            return response()->json(['message' => 'FORBIDDEN'], 403);
        }

        return response()->json($this->games->buildClientState($game, $player));
    }

    public function play(Request $request, Game $game): JsonResponse
    {
        /** @var Player $player */
        $player = $request->attributes->get('player');
        $card = $request->input('card');
        if (! is_string($card)) {
            return response()->json(['message' => 'INVALID_CARD'], 422);
        }

        try {
            $game = $this->games->playCard($player, $game, $card);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($this->games->buildClientState($game, $player));
    }

    public function endTurn(Request $request, Game $game): JsonResponse
    {
        /** @var Player $player */
        $player = $request->attributes->get('player');

        try {
            $game = $this->games->endTurn($player, $game);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($this->games->buildClientState($game, $player));
    }
}
