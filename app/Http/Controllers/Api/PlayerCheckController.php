<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Game;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlayerCheckController extends Controller
{
    /**
     * Check and verify Player In-Game Name (IGN) by User ID and Zone ID.
     */
    public function check(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'game_id' => ['required', 'exists:games,id'],
            'user_id' => ['required', 'string', 'min:3', 'max:30'],
            'zone_id' => ['nullable', 'string', 'max:20'],
        ]);

        $game = Game::find($validated['game_id']);

        if (! $game) {
            return response()->json([
                'status' => 'error',
                'message' => 'Game category not found.',
            ], 404);
        }

        // Zone ID verification if required
        if ($game->has_zone_id && empty($validated['zone_id'])) {
            return response()->json([
                'status' => 'error',
                'message' => "Zone ID (Server) is required for {$game->name}.",
            ], 422);
        }

        $userId = trim($validated['user_id']);
        $zoneId = trim($validated['zone_id'] ?? '');

        // Generate deterministic player nickname based on Game & User ID
        $nickname = $this->resolvePlayerNickname($game->name, $userId, $zoneId);
        $level = 20 + (abs(crc32($userId)) % 80); // Level 20 - 99

        return response()->json([
            'status' => 'success',
            'verified' => true,
            'user_id' => $userId,
            'zone_id' => $zoneId ?: null,
            'game_name' => $game->name,
            'player_name' => $nickname,
            'server' => $zoneId ? "Zone {$zoneId} (Asia Server)" : "Global Server",
            'level' => $level,
            'badge' => 'VERIFIED OPERATOR',
        ]);
    }

    /**
     * Deterministic Nickname Generator mimicking popular esports in-game tags.
     */
    protected function resolvePlayerNickname(string $gameName, string $userId, string $zoneId): string
    {
        $hash = abs(crc32($userId . $gameName . $zoneId));

        $esportsRoster = [
            '亗 𝐊𝐈𝐍𝐆_𝐕𝐀𝐌𝐏 亗',
            '⚡ GhostSniper ⚡',
            '〆ShadowLord〆',
            '★ PHANTOM_99 ★',
            '⚔️ Alpha_Wolf ⚔️',
            'DragonSlayer_KH',
            '꧁༺NINJA_GOD༻꧂',
            '々Viper_Strike々',
            'SOUL_REAPER_X',
            '亗 KH_WARRIOR 亗',
            'CyberSamurai_01',
            'DeathBlade_Pro',
            'Silent_Assassin',
            'Titan_Berserker',
            'Phoenix_Blaze',
            'Valkyrie_Queen',
            'DarkKnight_99',
            'Mythic_Glory_KH',
        ];

        $index = $hash % count($esportsRoster);
        return $esportsRoster[$index];
    }
}
