<?php
namespace App\Http\Controllers;
use App\Models\{Assistant,Conversation};
use App\Services\AssistantAccessService;
use Illuminate\Http\Request;
class ConversationController extends Controller {
 public function index(Request $r){return Conversation::where('organization_id',$r->attributes->get('organization_id'))->where('user_id',$r->user()->id)->latest()->paginate(25);}
 public function show(Request $r,Conversation $conversation,AssistantAccessService $access){
  $this->scope($r,$conversation);
  if($conversation->assistant_id){$assistant=Assistant::where('organization_id',$conversation->organization_id)->findOrFail($conversation->assistant_id);abort_unless($access->allows($assistant,(int)$r->user()->id,(int)$conversation->organization_id),404);}
  return $conversation->load('messages:id,conversation_id,role,content,metadata,created_at');
 }
 public function destroy(Request $r,Conversation $conversation){$this->scope($r,$conversation);$conversation->delete();return response()->noContent();}
 private function scope(Request $r,Conversation $c):void{abort_unless((int)$c->organization_id===(int)$r->attributes->get('organization_id')&&(int)$c->user_id===(int)$r->user()->id,404);}
}
