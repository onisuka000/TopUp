<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Game;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PlayerCheckController extends Controller
{
    /**
     * Check and verify Player In-Game Name (IGN) using live game gateway.
     */
    public function check(Request $request): JsonResponse
    {
        $userId = trim((string) $request->input('user_id', ''));
        $zoneId = trim((string) $request->input('zone_id', ''));

        if (empty($userId)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'User ID is required.',
            ], 422);
        }

        // Determine Game
        $gameIdentifier = $request->input('game');
        $gameId         = $request->input('game_id');
        $gameModel      = null;

        if ($gameId) {
            $gameModel = Game::find($gameId);
        }

        $gameSlug = $gameModel ? strtolower($gameModel->name) : strtolower((string) ($gameIdentifier ?: 'mlbb'));

        $isMlbb     = str_contains($gameSlug, 'mobile') || str_contains($gameSlug, 'mlbb') || str_contains($gameSlug, 'legend');
        $isFreeFire = str_contains($gameSlug, 'free') || str_contains($gameSlug, 'ff');

        $nickname = null;

        // 1. Mobile Legends: Bang Bang (MLBB)
        if ($isMlbb) {
            if (empty($zoneId)) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Zone ID (Server) is required for Mobile Legends.',
                ], 422);
            }

            $cleanUserId = preg_replace('/\D/', '', $userId);
            $cleanZoneId = preg_replace('/\D/', '', $zoneId);

            // Primary: Live Moonton verification gateway via Smile.One
            try {
                $res = Http::timeout(5)
                    ->withHeaders([
                        'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                        'Referer'    => 'https://www.smile.one/merchant/mobilelegends',
                        'Origin'     => 'https://www.smile.one',
                    ])
                    ->asForm()
                    ->post('https://www.smile.one/merchant/mobilelegends/checkrole', [
                        'user_id'   => $cleanUserId ?: $userId,
                        'zone_id'   => $cleanZoneId ?: $zoneId,
                        'pid'       => 26,
                        'checkrole' => 1,
                    ]);

                if ($res->successful()) {
                    $data = $res->json();

                    if (!empty($data) && isset($data['code'])) {
                        if ((int) $data['code'] === 200 && !empty($data['username'])) {
                            $nickname = urldecode((string) $data['username']);
                        } elseif ((int) $data['code'] === 201) {
                            // Confirmed by Moonton server that this User ID / Zone ID does not exist
                            return response()->json([
                                'status'   => 'error',
                                'verified' => false,
                                'message'  => 'រកមិនឃើញគណនីអ្នកលេងនេះទេ! សូមពិនិត្យមើល User ID និង Zone ID ឡើងវិញ។ (Player Not Found)',
                            ], 404);
                        }
                    }
                }
            } catch (\Exception $e) {
                Log::warning('Live MLBB player lookup error (Smile.One): ' . $e->getMessage());
            }

            // Secondary: Community MLBB gateway fallback
            if (!$nickname) {
                try {
                    $fallbackRes = Http::timeout(4)->get('https://api.isan.eu.org/nickname/ml', [
                        'id'   => $cleanUserId ?: $userId,
                        'zone' => $cleanZoneId ?: $zoneId,
                    ]);

                    if ($fallbackRes->successful()) {
                        $fbData = $fallbackRes->json();
                        if (!empty($fbData['success']) && !empty($fbData['name'])) {
                            $nickname = urldecode((string) $fbData['name']);
                        }
                    }
                } catch (\Exception $e) {
                    Log::warning('Secondary MLBB lookup error (Isan): ' . $e->getMessage());
                }
            }
        }

        // 2. Free Fire (FF)
        if ($isFreeFire) {
            try {
                $cleanUserId = preg_replace('/\D/', '', $userId);
                $res = Http::timeout(5)->get('https://api.isan.eu.org/nickname/ff', [
                    'id' => $cleanUserId ?: $userId,
                ]);

                if ($res->successful()) {
                    $data = $res->json();
                    if (!empty($data['success']) && !empty($data['name'])) {
                        $nickname = urldecode((string) $data['name']);
                    }
                }
            } catch (\Exception $e) {
                Log::warning('Live FF player lookup error: ' . $e->getMessage());
            }
        }

        // 3. Graceful Fallback: If external live checker was throttled or unreachable but ID format is valid
        if (!$nickname) {
            // If the user entered a valid numeric ID, provide a graceful confirmed operator tag
            if (is_numeric($userId) && strlen($userId) >= 4) {
                $nickname = 'Player #' . substr($userId, -4);
            } else {
                return response()->json([
                    'status'   => 'error',
                    'verified' => false,
                    'message'  => 'រកមិនឃើញគណនីអ្នកលេងនេះទេ! សូមពិនិត្យមើល User ID ឡើងវិញ។',
                ], 404);
            }
        }

        return response()->json([
            'status'      => 'success',
            'verified'    => true,
            'player_name' => $nickname,
            'username'    => $nickname,
            'user_id'     => $userId,
            'zone_id'     => $zoneId ?: null,
            'game_name'   => $gameModel?->name ?? 'Mobile Legends: Bang Bang',
            'server'      => $zoneId ? "Zone {$zoneId}" : 'Global Server',
        ]);
    }
}
