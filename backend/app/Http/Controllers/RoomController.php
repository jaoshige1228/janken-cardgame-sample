<?php

namespace App\Http\Controllers;

use App\Services\GameStateService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

class RoomController extends Controller
{
    public function __construct(
        private GameStateService $games,
    ) {}

    public function store(): JsonResponse
    {
        $payload = $this->games->createRoom();

        return response()->json($payload);
    }

    public function join(string $code): JsonResponse
    {
        try {
            $payload = $this->games->joinRoom(strtoupper($code));
        } catch (ModelNotFoundException) {
            return response()->json(['message' => 'ROOM_NOT_FOUND'], 404);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($payload);
    }
}
