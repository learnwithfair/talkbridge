<?php
namespace RahatulRabbi\TalkBridge\Models;
use Illuminate\Database\Eloquent\Model;
class GroupSettings extends Model {
    protected $guarded = [];
    protected $hidden  = ['created_at','updated_at','type'];
    protected $casts   = ['allow_members_to_send_messages'=>'boolean','allow_members_to_add_remove_participants'=>'boolean','allow_members_to_change_group_info'=>'boolean','admins_must_approve_new_members'=>'boolean','allow_invite_users_via_link'=>'boolean'];
    public function conversation() { return $this->belongsTo(Conversation::class); }
}
