<?php
use Illuminate\Support\Facades\Route;
use RahatulRabbi\TalkBridge\Http\Controllers\Api\V1\Chat\ConversationController;
use RahatulRabbi\TalkBridge\Http\Controllers\Api\V1\Chat\MessageController;
use RahatulRabbi\TalkBridge\Http\Controllers\Api\V1\Chat\GroupController;
use RahatulRabbi\TalkBridge\Http\Controllers\Api\V1\Chat\ReactionController;
use RahatulRabbi\TalkBridge\Http\Controllers\Api\V1\Chat\UserBlockController;

// Conversations
Route::get('conversations/{conversation}/media', [ConversationController::class, 'mediaLibrary']);
Route::apiResource('conversations', ConversationController::class)->only(['index', 'store', 'destroy']);
Route::post('conversations/private', [ConversationController::class, 'startPrivateConversation']);

// Messages
Route::get('messages/{conversation}/pinned-messages', [MessageController::class, 'getAllPinnedMessages']);
Route::apiResource('messages', MessageController::class)->only(['store', 'show', 'update']);

Route::prefix('messages')->controller(MessageController::class)->group(function () {
    Route::delete('delete-for-me',             'deleteForMe');
    Route::delete('delete-for-everyone',        'deleteForEveryone');
    Route::post('mark-seen',                   'markSeen');
    Route::get('seen/{conversation}',          'markAsSeen');
    Route::get('delivered/{conversation}',     'markAsDelivered');
    Route::post('{message}/forward',           'forward');
    Route::post('{message}/toggle-pin',        'pinToggleMessage');
});

// Reactions
Route::controller(ReactionController::class)->group(function () {
    Route::post('messages/{message}/reaction', 'toggleReaction');
    Route::get('messages/{message}/reaction',  'index');
});

// Group management
Route::prefix('group/{conversation}')->controller(GroupController::class)->group(function () {
    Route::post('update',             'update');
    Route::post('members/add',        'addMembers');
    Route::post('members/remove',     'removeMember');
    Route::get('members',             'getMembers');
    Route::post('admins/add',         'addAdmins');
    Route::post('admins/remove',      'removeAdmins');
    Route::post('mute',               'muteToggleGroup');
    Route::post('leave',              'leaveGroup');
    Route::delete('delete-group',     'deleteGroup');
    Route::post('regenerate-invite',  'regenerateInvite');
});

Route::get('/accept-invite/{token}', [GroupController::class, 'acceptInvite']);

// Users
Route::controller(UserBlockController::class)->group(function () {
    Route::get('online-users',                   'onlineUsers');
    Route::get('available-users',                'index');
    Route::post('users/{user}/block-toggle',     'toggleBlock');
    Route::post('users/{user}/restrict-toggle',  'toggleRestrict');
});
