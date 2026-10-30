<?php

use App\Http\Controllers\Albums\AlbumController;
use App\Http\Controllers\Albums\PhotoController;
use App\Http\Controllers\Auth\ProfileSetupController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\Berdua\BerduaController;
use App\Http\Controllers\Berdua\BudgetController;
use App\Http\Controllers\Berdua\CheckinController;
use App\Http\Controllers\Berdua\DreamController;
use App\Http\Controllers\Berdua\PartnerController;
use App\Http\Controllers\Berdua\PhotoboothController;
use App\Http\Controllers\Berdua\ArenaController;
use App\Http\Controllers\Berdua\PlayController;
use App\Http\Controllers\Berdua\PlaylistController;
use App\Http\Controllers\Berdua\ReflectionController;
use App\Http\Controllers\Berdua\LocationController;
use App\Http\Controllers\Berdua\RelationshipMapController;
use App\Http\Controllers\Berdua\YearController;
use App\Http\Controllers\Growth\HabitController;
use App\Http\Controllers\Berdua\CapsuleController;
use App\Http\Controllers\Berdua\ListController;
use App\Http\Controllers\Berdua\MomentsController;
use App\Http\Controllers\Berdua\PollController;
use App\Http\Controllers\AndroidAppController;
use App\Http\Controllers\NativeAppController;
use App\Http\Controllers\Berdua\QuestionController;
use App\Http\Controllers\Calendar\CalendarController;
use App\Http\Controllers\Chat\ChatController;
use App\Http\Controllers\Couple\CoupleSpaceController;
use App\Http\Controllers\Couple\InvitationController;
use App\Http\Controllers\Couple\JoinController;
use App\Http\Controllers\Couple\LeaveController;
use App\Http\Controllers\Couple\OnboardingController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Journal\JournalController;
use App\Http\Controllers\Mood\MoodController;
use App\Http\Controllers\MySpaceController;
use App\Http\Controllers\Notifications\NotificationController;
use App\Http\Controllers\OurSpaceController;
use App\Http\Controllers\Push\PushSubscriptionController;
use App\Http\Controllers\Settings\AccountController;
use App\Http\Controllers\Settings\AvatarController;
use App\Http\Controllers\Settings\BackgroundController;
use App\Http\Controllers\Settings\E2eController;
use App\Http\Controllers\Settings\SettingsController;
use App\Http\Controllers\Tasks\TaskController;
use App\Http\Controllers\WidgetController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::pattern('task', '[0-9]+');
Route::pattern('event', '[0-9]+');
Route::pattern('entry', '[0-9]+');
Route::pattern('album', '[0-9]+');
Route::pattern('photo', '[0-9]+');
foreach (['milestone', 'item', 'capsule', 'poll'] as $parameter) {
    Route::pattern($parameter, '[0-9]+');
}

Route::get('/', fn () => auth()->check() ? redirect()->route('dashboard') : Inertia::render('Welcome'))->name('home');

// The Android app: download page, version check (the app uses it to offer updates) and the file itself.
Route::get('/aplikasi', [AndroidAppController::class, 'page'])->name('android.page');
Route::get('/aplikasi/versi', [AndroidAppController::class, 'version'])->middleware('throttle:60,1,android.version')->name('android.version');
Route::get('/aplikasi/build', fn () => response()->json(['build' => \App\Support\BuildId::current()])->header('Cache-Control', 'no-store'))->middleware('throttle:60,1,android.build')->name('android.build');
Route::get('/aplikasi/LoveyNest.apk', [AndroidAppController::class, 'apk'])->middleware('throttle:20,1,android.apk')->name('android.apk');

Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->middleware('throttle:register');
    Route::get('/login', [SessionController::class, 'create'])->name('login');
    Route::post('/login', [SessionController::class, 'store']);
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [SessionController::class, 'destroy'])->name('logout');

    Route::get('/profil/lengkapi', [ProfileSetupController::class, 'show'])->name('profile.setup');
    Route::post('/profil/lengkapi', [ProfileSetupController::class, 'store'])->middleware('throttle:20,1,profile.setup');

    Route::get('/dashboard', DashboardController::class)->middleware('profile.complete')->name('dashboard');
    Route::get('/my-space', MySpaceController::class)->name('my-space');

    Route::middleware('no-couple')->group(function () {
        Route::get('/onboarding', [OnboardingController::class, 'show'])->middleware('profile.complete')->name('onboarding');
        Route::post('/couple', [CoupleSpaceController::class, 'store'])->name('couple.store');
        Route::get('/couple/join', fn () => redirect()->route('onboarding'));
        Route::post('/couple/join', [JoinController::class, 'preview'])->middleware('throttle:couple-join')->name('couple.join');
        Route::post('/couple/join/confirm', [JoinController::class, 'store'])->middleware('throttle:couple-join')->name('couple.join.confirm');
    });

    Route::middleware('couple')->group(function () {
        Route::get('/our-space', OurSpaceController::class)->name('our-space');
        Route::get('/couple/invite', [InvitationController::class, 'show'])->name('couple.invite');
        Route::post('/couple/invitations', [InvitationController::class, 'store'])->middleware('throttle:invitations')->name('couple.invitations.store');
        Route::delete('/couple', [LeaveController::class, 'destroy'])->name('couple.leave');
        Route::delete('/couple/invitations', [InvitationController::class, 'destroy'])->name('couple.invitations.destroy');

        Route::get('/nudges/pending', [BerduaController::class, 'pendingNudges'])->middleware('throttle:90,1,nudges.pending')->name('nudges.pending');
        Route::post('/nudges/{nudge}/seen', [BerduaController::class, 'seenNudge'])->name('nudges.seen');
        Route::post('/nudges', [BerduaController::class, 'nudge'])->middleware('throttle:30,1,nudges.store')->name('nudges.store');
        Route::get('/momen', [MomentsController::class, 'index'])->name('moments');
        Route::post('/momen/jadian', [MomentsController::class, 'anniversary'])->name('moments.anniversary');
        Route::post('/momen', [MomentsController::class, 'store'])->name('moments.store');
        Route::patch('/momen/{milestone}', [MomentsController::class, 'update'])->name('moments.update');
        Route::delete('/momen/{milestone}', [MomentsController::class, 'destroy'])->name('moments.destroy');
        Route::get('/pasangan', PartnerController::class)->name('partner');
        Route::get('/tahun-kita', YearController::class)->name('year');
        Route::get('/peta-hubungan', RelationshipMapController::class)->name('relmap');
        Route::get('/lokasi', [LocationController::class, 'show'])->name('location');
        Route::get('/lokasi/data', [LocationController::class, 'data'])->middleware('throttle:60,1,location.data')->name('location.data');
        Route::post('/lokasi/bagikan', [LocationController::class, 'share'])->middleware('throttle:30,1,location.share')->name('location.share');
        Route::delete('/lokasi/bagikan', [LocationController::class, 'stop'])->name('location.stop');
        Route::get('/pertanyaan', [QuestionController::class, 'index'])->name('question');
        Route::post('/pertanyaan', [QuestionController::class, 'store'])->middleware('throttle:30,1,question.store')->name('question.store');
        Route::get('/kapsul', [CapsuleController::class, 'index'])->name('capsules');
        Route::post('/kapsul', [CapsuleController::class, 'store'])->middleware('throttle:30,1,capsules.store')->name('capsules.store');
        Route::delete('/kapsul/{capsule}', [CapsuleController::class, 'destroy'])->name('capsules.destroy');
        Route::get('/photobooth', [PhotoboothController::class, 'index'])->name('photobooth');
        Route::post('/photobooth', [PhotoboothController::class, 'store'])->middleware('throttle:20,1,photobooth.store')->name('photobooth.store');
        Route::post('/photobooth/{session}/foto', [PhotoboothController::class, 'shot'])->whereNumber('session')->middleware('throttle:40,1,photobooth.shot')->name('photobooth.shot');
        Route::post('/photobooth/{session}/strip', [PhotoboothController::class, 'strip'])->whereNumber('session')->middleware('throttle:20,1,photobooth.strip')->name('photobooth.strip');
        Route::delete('/photobooth/{session}', [PhotoboothController::class, 'destroy'])->whereNumber('session')->name('photobooth.destroy');
        Route::get('/photobooth/foto/{shot}', [PhotoboothController::class, 'file'])->whereNumber('shot')->name('photobooth.file');
        Route::get('/voting', [PollController::class, 'index'])->name('polls');
        Route::post('/voting', [PollController::class, 'store'])->middleware('throttle:30,1,polls.store')->name('polls.store');
        Route::post('/voting/{poll}/vote', [PollController::class, 'vote'])->name('polls.vote');
        Route::post('/voting/{poll}/close', [PollController::class, 'close'])->name('polls.close');
        Route::delete('/voting/{poll}', [PollController::class, 'destroy'])->name('polls.destroy');

        Route::get('/chat', [ChatController::class, 'show'])->name('chat');
        Route::get('/chat/messages', [ChatController::class, 'index'])->middleware('throttle:chat-poll')->name('chat.messages');
        Route::post('/chat/messages', [ChatController::class, 'store'])->middleware('throttle:chat-send')->name('chat.send');
        Route::post('/chat/messages/{message}/reactions', [ChatController::class, 'react'])->whereNumber('message')->middleware('throttle:chat-send')->name('chat.react');
        Route::patch('/chat/messages/{message}', [ChatController::class, 'update'])->whereNumber('message')->middleware('throttle:chat-send')->name('chat.edit');
        Route::delete('/chat/messages/{message}', [ChatController::class, 'destroy'])->whereNumber('message')->middleware('throttle:chat-send')->name('chat.delete');
        Route::post('/chat/messages/{message}/pin', [ChatController::class, 'pin'])->whereNumber('message')->middleware('throttle:chat-send')->name('chat.pin');
        Route::get('/chat/love-notes', [ChatController::class, 'loveNotes'])->middleware('throttle:chat-poll')->name('chat.notes');
        Route::post('/chat/love-notes', [ChatController::class, 'scheduleLoveNote'])->middleware('throttle:chat-send')->name('chat.notes.store');
        Route::delete('/chat/love-notes/{note}', [ChatController::class, 'cancelLoveNote'])->whereNumber('note')->middleware('throttle:chat-send')->name('chat.notes.cancel');
        Route::post('/chat/mute', [ChatController::class, 'mute'])->middleware('throttle:chat-send')->name('chat.mute');
        Route::get('/chat/search', [ChatController::class, 'search'])->middleware('throttle:chat-poll')->name('chat.search');
        Route::post('/chat/read', [ChatController::class, 'read'])->middleware('throttle:chat-poll')->name('chat.read');
        Route::post('/chat/typing', [ChatController::class, 'typing'])->middleware('throttle:chat-typing')->name('chat.typing');
        Route::get('/chat/audio/{message}', [ChatController::class, 'audio'])->whereNumber('message')->name('chat.audio');
        Route::get('/chat/photos/{message}/{variant}', [ChatController::class, 'photo'])->whereNumber('message')->name('chat.photo');
    });

    Route::get('/tasks', [TaskController::class, 'index'])->name('tasks.index');
    Route::post('/tasks', [TaskController::class, 'store'])->name('tasks.store');
    Route::patch('/tasks/{task}', [TaskController::class, 'update'])->name('tasks.update');
    Route::patch('/tasks/{task}/status', [TaskController::class, 'updateStatus'])->name('tasks.status');
    Route::patch('/tasks/{task}/subtasks/{subtask}', [TaskController::class, 'toggleSubtask'])->whereNumber('subtask')->name('tasks.subtask');
    Route::delete('/tasks/{task}', [TaskController::class, 'destroy'])->name('tasks.destroy');

    Route::get('/calendar', [CalendarController::class, 'index'])->name('calendar');
    Route::post('/events', [CalendarController::class, 'store'])->name('events.store');
    Route::patch('/events/{event}', [CalendarController::class, 'update'])->name('events.update');
    Route::post('/events/{event}/rsvp', [CalendarController::class, 'rsvp'])->middleware('throttle:60,1,events.rsvp')->name('events.rsvp');
    Route::patch('/events/{event}/rating', [CalendarController::class, 'rate'])->middleware('throttle:60,1,events.rate')->name('events.rate');
    Route::get('/riwayat-kencan', [CalendarController::class, 'history'])->middleware('couple')->name('dates.history');
    Route::delete('/events/{event}', [CalendarController::class, 'destroy'])->name('events.destroy');

    Route::get('/anggaran', [BudgetController::class, 'index'])->middleware('couple')->name('budget');
    Route::get('/anggaran/ekspor', [BudgetController::class, 'export'])->middleware('throttle:10,1,budget.export')->middleware('couple')->name('budget.export');
    Route::post('/anggaran', [BudgetController::class, 'store'])->middleware('throttle:60,1,budget.store')->name('budget.store');
    Route::post('/anggaran/lunas', [BudgetController::class, 'settle'])->middleware('throttle:20,1,budget.settle')->name('budget.settle');
    Route::put('/anggaran/bulanan', [BudgetController::class, 'budget'])->name('budget.monthly');
    Route::patch('/anggaran/{expense}', [BudgetController::class, 'update'])->whereNumber('expense')->name('budget.update');
    Route::post('/anggaran/{expense}/batal', [BudgetController::class, 'void'])->whereNumber('expense')->name('budget.void');
    Route::post('/tabungan', [BudgetController::class, 'storeGoal'])->middleware('throttle:30,1,savings.store')->name('savings.store');
    Route::post('/tabungan/{goal}/setor', [BudgetController::class, 'saveToGoal'])->whereNumber('goal')->middleware('throttle:60,1,savings.add')->name('savings.add');
    Route::delete('/tabungan/{goal}', [BudgetController::class, 'destroyGoal'])->whereNumber('goal')->name('savings.destroy');

    Route::get('/playlist', [PlaylistController::class, 'index'])->middleware('couple')->name('playlist');
    Route::post('/playlist', [PlaylistController::class, 'store'])->middleware('throttle:60,1,playlist.store')->name('playlist.store');
    Route::patch('/playlist/{item}', [PlaylistController::class, 'update'])->whereNumber('item')->name('playlist.update');
    Route::delete('/playlist/{item}', [PlaylistController::class, 'destroy'])->whereNumber('item')->name('playlist.destroy');

    Route::get('/impian', [DreamController::class, 'index'])->middleware('couple')->name('dreams');
    Route::post('/impian', [DreamController::class, 'store'])->middleware('throttle:30,1,dreams.store')->name('dreams.store');
    Route::post('/impian/{dream}', [DreamController::class, 'update'])->whereNumber('dream')->middleware('throttle:60,1,dreams.update')->name('dreams.update');
    Route::delete('/impian/{dream}', [DreamController::class, 'destroy'])->whereNumber('dream')->name('dreams.destroy');
    Route::get('/impian/{dream}/foto', [DreamController::class, 'image'])->whereNumber('dream')->name('dreams.image');

    Route::get('/checkin', [CheckinController::class, 'index'])->middleware('couple')->name('checkin');
    Route::put('/checkin', [CheckinController::class, 'save'])->middleware('throttle:30,1,checkin.save')->name('checkin.save');

    Route::get('/refleksi', [ReflectionController::class, 'index'])->middleware('couple')->name('reflection');
    Route::put('/refleksi', [ReflectionController::class, 'save'])->middleware('throttle:30,1,reflection.save')->name('reflection.save');
    Route::post('/kesepakatan', [ReflectionController::class, 'storeAgreement'])->middleware('throttle:30,1,agreements.store')->name('agreements.store');
    Route::patch('/kesepakatan/{agreement}', [ReflectionController::class, 'updateAgreement'])->whereNumber('agreement')->name('agreements.update');
    Route::delete('/kesepakatan/{agreement}', [ReflectionController::class, 'destroyAgreement'])->whereNumber('agreement')->name('agreements.destroy');

    Route::get('/main', [PlayController::class, 'index'])->middleware('couple')->name('play');
    Route::post('/main/kuis', [PlayController::class, 'answer'])->middleware('throttle:120,1,play.answer')->name('play.answer');
    Route::post('/main/hadiah', [PlayController::class, 'rewardStore'])->middleware('throttle:60,1,play.reward.store')->name('play.reward.store');
    Route::patch('/main/hadiah/{reward}', [PlayController::class, 'rewardToggle'])->middleware('throttle:60,1,play.reward.toggle')->name('play.reward.toggle');
    Route::delete('/main/hadiah/{reward}', [PlayController::class, 'rewardDestroy'])->middleware('throttle:60,1,play.reward.destroy')->name('play.reward.destroy');
    Route::post('/main/kuis/ai', [PlayController::class, 'aiGenerate'])->middleware('throttle:6,1,play.ai')->name('play.ai');
    Route::delete('/main/kuis/ai', [PlayController::class, 'aiClear'])->middleware('throttle:10,1,play.ai.clear')->name('play.ai.clear');
    Route::post('/main/tantangan', [PlayController::class, 'challenge'])->middleware('throttle:120,1,play.challenge')->name('play.challenge');

    Route::middleware('couple')->prefix('arena')->group(function () {
        Route::get('/', [ArenaController::class, 'index'])->name('arena');
        Route::get('/status', [ArenaController::class, 'status'])->middleware('throttle:120,1,arena.status')->name('arena.status');
        Route::post('/undang', [ArenaController::class, 'invite'])->middleware('throttle:20,1,arena.invite')->name('arena.invite');
        Route::post('/skor', [ArenaController::class, 'score'])->middleware('throttle:60,1,arena.score')->name('arena.score');
        Route::get('/sesi/{session}', [ArenaController::class, 'session'])->whereNumber('session')->name('arena.session');
        Route::get('/sesi/{session}/state', [ArenaController::class, 'state'])->whereNumber('session')->middleware('throttle:240,1,arena.state')->name('arena.state');
        Route::post('/sesi/{session}/aksi', [ArenaController::class, 'act'])->whereNumber('session')->middleware('throttle:240,1,arena.act')->name('arena.act');
        Route::get('/sesi/{session}/suara', [ArenaController::class, 'voicePoll'])->whereNumber('session')->middleware('throttle:150,1,arena.voice.poll')->name('arena.voice.poll');
        Route::post('/sesi/{session}/suara', [ArenaController::class, 'voiceSend'])->whereNumber('session')->middleware('throttle:150,1,arena.voice.send')->name('arena.voice.send');
        Route::post('/sesi/{session}/terima', [ArenaController::class, 'accept'])->whereNumber('session')->middleware('throttle:30,1,arena.accept')->name('arena.accept');
        Route::post('/sesi/{session}/tolak', [ArenaController::class, 'decline'])->whereNumber('session')->middleware('throttle:30,1,arena.decline')->name('arena.decline');
        Route::post('/sesi/{session}/batal', [ArenaController::class, 'cancel'])->whereNumber('session')->middleware('throttle:30,1,arena.cancel')->name('arena.cancel');
        Route::get('/{game}', [ArenaController::class, 'play'])->where('game', 'kartu|puzzle|tangkap|ngobrol|penalti')->name('arena.play');
    });

    Route::get('/kebiasaan', [HabitController::class, 'index'])->name('growth');
    Route::post('/kebiasaan', [HabitController::class, 'store'])->middleware('throttle:60,1,habits.store')->name('habits.store');
    Route::patch('/kebiasaan/{habit}', [HabitController::class, 'update'])->whereNumber('habit')->name('habits.update');
    Route::delete('/kebiasaan/{habit}', [HabitController::class, 'destroy'])->whereNumber('habit')->name('habits.destroy');
    Route::post('/kebiasaan/{habit}/centang', [HabitController::class, 'check'])->whereNumber('habit')->middleware('throttle:120,1,habits.check')->name('habits.check');
    Route::post('/tujuan', [HabitController::class, 'storeGoal'])->middleware('throttle:60,1,goals.store')->name('goals.store');
    Route::patch('/tujuan/{goal}', [HabitController::class, 'updateGoal'])->whereNumber('goal')->name('goals.update');
    Route::delete('/tujuan/{goal}', [HabitController::class, 'destroyGoal'])->whereNumber('goal')->name('goals.destroy');

    Route::get('/berdua', [BerduaController::class, 'index'])->name('berdua');
    Route::get('/daftar', [ListController::class, 'index'])->name('lists.index');
    Route::post('/daftar', [ListController::class, 'store'])->middleware('throttle:60,1,lists.store')->name('lists.store');
    Route::patch('/daftar/{item}', [ListController::class, 'update'])->name('lists.update');
    Route::delete('/daftar/{item}', [ListController::class, 'destroy'])->name('lists.destroy');
    Route::get('/ide', fn () => Inertia::render('Berdua/Ideas'))->name('ideas');

    Route::get('/mood', [MoodController::class, 'index'])->name('mood');
    Route::post('/mood', [MoodController::class, 'store'])->middleware('throttle:30,1,mood.store')->name('mood.store');

    Route::get('/journal', [JournalController::class, 'index'])->name('journal.index');
    Route::post('/journal', [JournalController::class, 'store'])->middleware('throttle:30,1,journal.store')->name('journal.store');
    Route::patch('/journal/{entry}', [JournalController::class, 'update'])->name('journal.update');
    Route::post('/journal/{entry}/read', [JournalController::class, 'read'])->name('journal.read');
    Route::delete('/journal/{entry}', [JournalController::class, 'destroy'])->name('journal.destroy');

    Route::get('/albums', [AlbumController::class, 'index'])->name('albums.index');
    Route::post('/albums', [AlbumController::class, 'store'])->name('albums.store');
    Route::get('/albums/{album}', [AlbumController::class, 'show'])->name('albums.show');
    Route::patch('/albums/{album}', [AlbumController::class, 'update'])->name('albums.update');
    Route::delete('/albums/{album}', [AlbumController::class, 'destroy'])->name('albums.destroy');
    Route::post('/albums/{album}/photos', [PhotoController::class, 'store'])->middleware('throttle:20,1,photos.store')->name('photos.store');
    Route::delete('/photos/{photo}', [PhotoController::class, 'destroy'])->name('photos.destroy');
    Route::patch('/photos/{photo}', [PhotoController::class, 'details'])->middleware('throttle:120,1,photos.details')->name('photos.details');
    Route::post('/albums/{album}/cover', [AlbumController::class, 'cover'])->middleware('throttle:30,1,albums.cover')->name('albums.cover');
    Route::get('/photos/{photo}/{variant}', [PhotoController::class, 'file'])->name('photos.file');

    Route::get('/widget', WidgetController::class)->name('widget');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications');
    Route::get('/notifications/latest', [NotificationController::class, 'latest'])->name('notifications.latest');
    Route::get('/notifications/{notification}/open', [NotificationController::class, 'open'])->whereUuid('notification')->name('notifications.open');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');

    Route::post('/push/subscriptions', [PushSubscriptionController::class, 'store'])->middleware('throttle:push')->name('push.store');
    Route::post('/push/fcm', [PushSubscriptionController::class, 'storeFcm'])->middleware('throttle:push')->name('push.fcm');
    Route::post('/push/subscriptions/status', [PushSubscriptionController::class, 'status'])->middleware('throttle:push')->name('push.status');
    Route::delete('/push/subscriptions', [PushSubscriptionController::class, 'destroy'])->name('push.destroy');
    Route::post('/push/test', [PushSubscriptionController::class, 'test'])->middleware('throttle:push-test')->name('push.test');

    Route::get('/settings', [SettingsController::class, 'show'])->name('settings');
    Route::get('/pengaturan', [SettingsController::class, 'hub'])->name('pengaturan');
    Route::get('/pengaturan/tampilan', [SettingsController::class, 'appearance'])->name('pengaturan.tampilan');
    Route::get('/pengaturan/notifikasi', [SettingsController::class, 'notifications'])->name('pengaturan.notifikasi');
    Route::get('/pengaturan/pasangan', [SettingsController::class, 'couple'])->name('pengaturan.pasangan');
    Route::get('/pengaturan/tentang', [SettingsController::class, 'about'])->name('pengaturan.tentang');
    Route::get('/pengaturan/aplikasi', [NativeAppController::class, 'settings'])->name('pengaturan.aplikasi');
    Route::get('/aplikasi/pengingat', [NativeAppController::class, 'reminders'])->middleware('throttle:60,1,android.reminders')->name('android.reminders');
    Route::get('/aplikasi/widget', [NativeAppController::class, 'widget'])->middleware('throttle:60,1,android.widget')->name('android.widget');
    Route::patch('/settings/notifications', [SettingsController::class, 'updateNotifications'])->name('settings.notifications');
    Route::patch('/settings/theme', [SettingsController::class, 'updateTheme'])->name('settings.theme');
    Route::patch('/settings/digest', [SettingsController::class, 'updateDigest'])->name('settings.digest');
    Route::patch('/settings/quiet-hours', [SettingsController::class, 'updateQuietHours'])->name('settings.quiet');
    Route::patch('/settings/dashboard', [SettingsController::class, 'updateDashboard'])->name('settings.dashboard');
    Route::patch('/settings/profile', [SettingsController::class, 'updateProfile'])->name('settings.profile');
    Route::post('/settings/avatar', [AvatarController::class, 'store'])->middleware('throttle:10,1,settings.avatar')->name('settings.avatar');
    Route::patch('/settings/avatar-frame', [AvatarController::class, 'frame'])->name('settings.avatar.frame');
    Route::patch('/settings/profile-card', [AvatarController::class, 'card'])->name('settings.profile.card');
    Route::delete('/settings/avatar', [AvatarController::class, 'destroy'])->name('settings.avatar.destroy');
    Route::patch('/settings/background', [BackgroundController::class, 'update'])->name('settings.background');
    Route::post('/settings/background/photo', [BackgroundController::class, 'upload'])->middleware('throttle:10,1,settings.background.upload')->name('settings.background.upload');
    Route::delete('/settings/background/photo', [BackgroundController::class, 'destroy'])->name('settings.background.destroy');
    Route::get('/backgrounds/{user}/{variant?}', [BackgroundController::class, 'show'])->name('backgrounds.show');
    Route::get('/avatars/{user}', [AvatarController::class, 'show'])->name('avatars.show');
    Route::get('/settings/privasi', [AccountController::class, 'show'])->name('settings.privacy');
    Route::delete('/settings/privasi/sesi/{session}', [AccountController::class, 'revokeSession'])->where('session', '[A-Za-z0-9]+')->name('settings.session.revoke');
    Route::delete('/settings/privasi/push/{device}', [AccountController::class, 'revokePush'])->whereNumber('device')->name('settings.push.revoke');
    Route::post('/settings/privasi/keluar-semua', [AccountController::class, 'logoutEverywhere'])->middleware('throttle:6,1,settings.logout-all')->name('settings.logout-all');
    Route::get('/settings/privasi/ekspor', [AccountController::class, 'export'])->middleware('throttle:3,60,settings.export')->name('settings.export');
    Route::delete('/settings/privasi/akun', [AccountController::class, 'destroy'])->middleware('throttle:5,10,settings.delete-account')->name('settings.delete-account');
    Route::post('/e2e/devices', [E2eController::class, 'register'])->middleware('throttle:20,1,e2e.register')->name('e2e.register');
    Route::get('/e2e/status', [E2eController::class, 'status'])->middleware('throttle:120,1,e2e.status')->name('e2e.status');
    Route::post('/e2e/keys', [E2eController::class, 'createKey'])->middleware('throttle:10,1,e2e.keys')->name('e2e.keys');
    Route::post('/e2e/share', [E2eController::class, 'share'])->middleware('throttle:30,1,e2e.share')->name('e2e.share');
    Route::post('/e2e/password/unlock', [E2eController::class, 'unlock'])->middleware('throttle:8,1,e2e.unlock')->name('e2e.password.unlock');
    Route::post('/e2e/password/wraps', [E2eController::class, 'savePasswordWraps'])->middleware('throttle:10,1,e2e.password')->name('e2e.password.wraps');
    Route::delete('/e2e/devices/{device}', [E2eController::class, 'destroy'])->whereNumber('device')->name('e2e.devices.destroy');
    Route::put('/settings/password', [SettingsController::class, 'updatePassword'])->middleware('throttle:6,1,settings.password')->name('settings.password');
});
