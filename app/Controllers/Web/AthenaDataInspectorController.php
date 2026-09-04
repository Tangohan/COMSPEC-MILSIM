<?php
declare(strict_types=1);
namespace App\Controllers\Web;
use App\Core\Request;use App\Core\Response;
final class AthenaDataInspectorController
{
 public function index(Request $request,array $params=[]):Response{return Response::view('layout.main',['title'=>'Athena Data Inspector','content'=>'atak.data_inspector','athenaInspectorPage'=>true]);}
}
