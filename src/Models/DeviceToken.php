<?php
namespace RahatulRabbi\TalkBridge\Models;
use Illuminate\Database\Eloquent\Model;
class DeviceToken extends Model {
    protected $fillable = ['user_id','platform','token','meta'];
    protected $casts    = ['meta'=>'array'];
    protected $hidden   = ['user_id','meta','created_at'];
    public function user() { return $this->belongsTo(config('talkbridge.user_model')); }
}
