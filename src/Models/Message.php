<?php
namespace RahatulRabbi\TalkBridge\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class Message extends Model {
    use SoftDeletes;
    protected $guarded = [];
    protected $casts = ['is_deleted_for_everyone'=>'boolean','is_restricted'=>'boolean','is_pinned'=>'boolean','edited_at'=>'datetime','deleted_at'=>'datetime'];
    public function scopePinned($q) { return $q->where('is_pinned', true); }
    public function conversation()  { return $this->belongsTo(Conversation::class); }
    public function sender()        { return $this->belongsTo(config('talkbridge.user_model'), 'sender_id'); }
    public function receiver()      { return $this->belongsTo(config('talkbridge.user_model'), 'receiver_id'); }
    public function attachments()   { return $this->hasMany(MessageAttachment::class); }
    public function reactions()     { return $this->hasMany(MessageReaction::class); }
    public function statuses()      { return $this->hasMany(MessageStatus::class); }
    public function deletions()     { return $this->hasMany(MessageDeletion::class); }
    public function replyTo()       { return $this->belongsTo(Message::class, 'reply_to_message_id'); }
    public function forwardedFrom() { return $this->belongsTo(Message::class, 'forward_to_message_id'); }
}
