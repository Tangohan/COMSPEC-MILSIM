<?php

declare(strict_types=1);
namespace App\Controllers\Web;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\Tenant\TenantContext;

final class PatchStudioController
{
    public function index(Request $request,array $params=[]): Response
    {
        return Response::view('patch_studio.index',[
            'csrf'=>Csrf::token(),'api'=>url('api/patch-studio'),
            'recoveryKey'=>TenantContext::id().':'.(int)Session::get('user_id'),
        ])->header('Cache-Control','no-store');
    }
}
