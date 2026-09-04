<?php
declare(strict_types=1);
namespace App\Controllers\Admin;
use App\Core\Request;use App\Core\Response;use App\Core\Session;use App\Repositories\AtakMapRepository;use App\Support\ModuleFeatureAccess;
final class AdminAthenaTacticalMapController
{
 public function __construct(private AtakMapRepository $maps){}
 public function index(Request $request,array $params=[]): Response {if(($r=ModuleFeatureAccess::guardAtak('manage')) instanceof Response)return $r;return Response::view('layout.main',['content'=>'admin.athena_tactical_map.index','title'=>'Carte tactique — ATHENA','tacticalMaps'=>$this->maps->getAll(),'tenantId'=>(int)Session::get('tenant_id')]);}
}
