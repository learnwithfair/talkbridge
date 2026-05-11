<?php
namespace RahatulRabbi\TalkBridge\Models;
use Illuminate\Database\Eloquent\Model;
class MessageStatus extends Model {
    protected $guarded = [];
    public function message() { return $this->belongsTo(Message::class); }
    public function user()    { return $this->belongsTo(config('talkbridge.user_model')); }
}
