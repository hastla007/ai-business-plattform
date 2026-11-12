<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class OrganizationMembersController extends Controller {
 public function index(Request $r){
  return DB::table('memberships')->join('users','users.id','=','memberships.user_id')->where('memberships.organization_id',$r->attributes->get('organization_id'))->orderBy('users.name')->get(['users.id','users.name','users.email','memberships.role']);
 }
}
