<?php

namespace App\Http\Controllers\Push;

use App\Http\Controllers\Controller;
use App\Http\Requests\Push\PushSubscriptionRequest;
use App\Models\PushSubscription;
use App\Services\Push\FcmSender;
use App\Services\Push\WebPushSender;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PushSubscriptionController extends Controller
{
    /** Session key holding this browser's endpoint hash, used to clean up on logout. */
    public const SESSION_KEY = 'push_endpoint_hash';

    /**
     * Saves (or refreshes) this device's subscription for the signed-in user.
     * A device that was registered by another account is moved over, so one
     * phone never receives two people's notifications.
     */
    public function store(PushSubscriptionRequest $request): JsonResponse
    {
        $data = $request->subscriptionData();
        $hash = PushSubscription::hashEndpoint($data['endpoint']);

        $subscription = DB::transaction(function () use ($request, $data, $hash) {
            $subscription = PushSubscription::where('endpoint_hash', $hash)->lockForUpdate()->first() ?? new PushSubscription(['endpoint_hash' => $hash]);
            $subscription->fill([...$data, 'user_id' => $request->user()->id]);
            $subscription->forceFill(['expired_at' => null, 'failure_count' => 0])->save();

            return $subscription;
        });

        $request->session()->put(self::SESSION_KEY, $hash);

        return response()->json(['status' => 'active', 'id' => $subscription->id], $subscription->wasRecentlyCreated ? 201 : 200);
    }

    /**
     * Saves the Android app's FCM token. It lives in the same table as browser subscriptions
     * (endpoint "fcm:<token>"), so the device list, logout clean-up and status work unchanged.
     */
    public function storeFcm(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:1024', 'regex:/^[A-Za-z0-9_:\-]+$/'],
            'device_label' => ['nullable', 'string', 'max:100'],
            'app' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ]);
        $endpoint = FcmSender::PREFIX.$data['token'];
        $hash = PushSubscription::hashEndpoint($endpoint);

        $subscription = DB::transaction(function () use ($request, $data, $endpoint, $hash) {
            $subscription = PushSubscription::where('endpoint_hash', $hash)->lockForUpdate()->first() ?? new PushSubscription(['endpoint_hash' => $hash]);
            $subscription->fill([
                'endpoint' => $endpoint,
                // Version code of the app, so the server knows how it wants its notifications (see FcmSender).
                'public_key' => (string) ($data['app'] ?? 0),
                'auth_token' => '',
                'content_encoding' => FcmSender::ENCODING,
                'device_label' => mb_substr(strip_tags($data['device_label'] ?? 'Aplikasi · Android'), 0, 100),
                'user_id' => $request->user()->id,
            ]);
            $subscription->forceFill(['expired_at' => null, 'failure_count' => 0])->save();

            return $subscription;
        });

        $request->session()->put(self::SESSION_KEY, $hash);

        return response()->json(['status' => 'active', 'id' => $subscription->id], $subscription->wasRecentlyCreated ? 201 : 200);
    }

    /** Status of this browser's subscription as the server sees it. */
    public function status(Request $request): JsonResponse
    {
        $data = $request->validate(['endpoint' => ['required', 'string', 'max:2048']]);
        $subscription = $request->user()->pushSubscriptions()->where('endpoint_hash', PushSubscription::hashEndpoint($data['endpoint']))->first();

        if ($subscription && ! $subscription->expired_at) {
            $request->session()->put(self::SESSION_KEY, $subscription->endpoint_hash);
        }

        return response()->json(['status' => match (true) {
            ! $subscription => 'unknown',
            $subscription->expired_at !== null => 'expired',
            default => 'active',
        }]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate(['endpoint' => ['required', 'string', 'max:2048']]);
        $hash = PushSubscription::hashEndpoint($data['endpoint']);

        // Only ever the caller's own row; someone else's endpoint is simply not found.
        $request->user()->pushSubscriptions()->where('endpoint_hash', $hash)->delete();
        if ($request->session()->get(self::SESSION_KEY) === $hash) {
            $request->session()->forget(self::SESSION_KEY);
        }

        return response()->json(['status' => 'removed']);
    }

    /** Sends a test push to every active device of the caller, right away. */
    public function test(Request $request, WebPushSender $sender, FcmSender $fcm): JsonResponse
    {
        if (! WebPushSender::isConfigured() && ! FcmSender::isConfigured()) {
            return response()->json(['message' => __('push.not_configured')], 503);
        }

        [$android, $subscriptions] = $request->user()->pushSubscriptions()->active()->get()->partition(fn ($s) => $s->content_encoding === FcmSender::ENCODING);
        if ($subscriptions->isEmpty() && $android->isEmpty()) {
            return response()->json(['message' => __('push.no_devices')], 422);
        }

        $payload = [
            'title' => __('notifications.test_title'),
            'body' => __('notifications.test_body'),
            'url' => '/pengaturan/notifikasi',
            'tag' => 'test',
        ];
        $result = $sender->send($subscriptions->values(), $payload, 300);
        $fromApp = $fcm->send($android->values(), $payload, 300);
        foreach (['sent', 'expired', 'failed'] as $key) {
            $result[$key] += $fromApp[$key];
        }
        if (! $result['sent'] && isset($fromApp['status'])) {
            $result['status'] = $fromApp['status'];
            $result['reason'] = $fromApp['reason'] ?? null;
        }

        return response()->json($result);
    }
}
