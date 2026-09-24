<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Round;
use App\Support\GuestLimits;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    /**
     * Current guest round limits per mode (settings overrides + defaults).
     */
    public function guestLimits()
    {
        return response()->json([
            'success' => true,
            'data' => collect(GuestLimits::MODES)->mapWithKeys(fn (string $mode) => [
                $mode => [
                    'mode' => $mode,
                    'name' => $this->modeLabel($mode),
                    'limit' => GuestLimits::limit($mode),
                    'default' => GuestLimits::default($mode),
                    'configured' => GuestLimits::hasOverride($mode),
                ],
            ])->all(),
        ]);
    }

    /**
     * Persist guest round limits per mode.
     */
    public function updateGuestLimits(Request $request)
    {
        $data = $request->validate([
            'sokwe' => ['required', 'integer', 'min:0'],
            'hera' => ['required', 'integer', 'min:0'],
            'tuja' => ['required', 'integer', 'min:0'],
        ]);

        foreach (GuestLimits::MODES as $mode) {
            GuestLimits::setLimit($mode, (int) $data[$mode]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Guest round limits updated.',
            'data' => null,
        ]);
    }

    /**
     * Restore config defaults for every mode (remove all overrides).
     */
    public function resetGuestLimits()
    {
        foreach (GuestLimits::MODES as $mode) {
            GuestLimits::clearLimit($mode);
        }

        return response()->json([
            'success' => true,
            'message' => 'Guest round limits reset to defaults.',
            'data' => null,
        ]);
    }

    protected function modeLabel(string $mode): string
    {
        return match ($mode) {
            Round::MODE_SOKWE => 'Sokwe',
            Round::MODE_HERA => 'Heraheza',
            Round::MODE_TUJA => 'Tujajure',
            default => $mode,
        };
    }
}