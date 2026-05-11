<?php
namespace RahatulRabbi\TalkBridge\Models;
use Illuminate\Database\Eloquent\Model;
class Conversation extends Model {
    protected $guarded = [];
    protected $casts   = ['created_at' => 'datetime', 'updated_at' => 'datetime'];
    public function participants() { return $this->hasMany(ConversationParticipant::class); }
    public function messages()     { return $this->hasMany(Message::class)->latest(); }
    public function lastMessage()  { return $this->hasOne(Message::class)->latestOfMany(); }
    public function groupSetting() { return $this->hasOne(GroupSettings::class); }
    public function unreadMessages() { return $this->hasMany(Message::class); }
    public function invites()      { return $this->hasMany(ConversationInvite::class); }
    public function activeInvites(){ return $this->hasMany(ConversationInvite::class)->where('is_active', true); }
    public function creator()      { return $this->belongsTo(config('talkbridge.user_model'), 'created_by'); }
    public function getInviteLinkAttribute() { return $this->activeInvites->sortByDesc('created_at')->first(); }
    public function otherParticipant($user) {
        return $this->participants->where('user_id', '!=', $user->id)->first()?->user;
    }
    public function canUserSendMessage(?ConversationParticipant $p = null): bool {
        if ($this->type === 'private') return true;
        if ($p && in_array($p->role, ['admin', 'super_admin'])) return true;
        return (bool) ($this->groupSetting?->allow_members_to_send_messages ?? true);
    }
}
