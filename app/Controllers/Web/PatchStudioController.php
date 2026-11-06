<?php

declare(strict_types=1);
namespace App\Controllers\Web;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\Tenant\TenantContext;
use App\Services\PatchStudio\PlanAccess;

final class PatchStudioController
{
    public function index(Request $request,array $params=[]): Response
    {
        if (!PlanAccess::allows(TenantContext::id())) {
            return Response::view('patch_studio.upgrade')->setStatusCode(403)->header('Cache-Control','private, no-store');
        }
        return Response::view('patch_studio.index',[
            'csrf'=>Csrf::token(),'api'=>url('api/patch-studio'),
            'recoveryKey'=>TenantContext::id().':'.(int)Session::get('user_id'),
        ])->header('Cache-Control','no-store');
    }
}
