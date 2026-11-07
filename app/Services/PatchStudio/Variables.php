<?php

declare(strict_types=1);

namespace App\Services\PatchStudio;

use App\Core\Container;
use App\Core\Gate;
use App\Repositories\UserRepository;
use App\Services\Atak\AtakOperatorPersonnelService;
use App\Services\Personnel\MemberNameService;
use App\Support\EffectifsLmsAccess;
use App\Support\OrgVisibilityCapabilities;

final class Variables
{
    /** Bounded search of actual active tenant members; visibility is enforced before returning identities. */
    public static function catalog(int $tenant,int $viewer,string $query=''): array
    {
        if (!EffectifsLmsAccess::allows(Gate::getInstance())) return self::resolve($tenant,$viewer,[$viewer]);
        $rows=Container::get(UserRepository::class)->listPersonnelDirectoryRich($tenant,mb_substr(trim($query),0,80),100);
        return self::resolve($tenant,$viewer,array_map(static fn(array $r): int => (int)$r['id'],$rows));
    }
    public static function resolve(int $tenant,int $viewer,array $ids): array
    {
        $ids=array_values(array_unique(array_map('intval',$ids)));
        if (count($ids)>100) throw new \InvalidArgumentException('100 membres maximum par série.');
        if (!EffectifsLmsAccess::allows(Gate::getInstance())) $ids=[$viewer];
        $users=Container::get(UserRepository::class);
        $rows=$users->listEffectifsRosterByIds($tenant,$ids); // impose appartenance au tenant
        $byId=[];
        foreach ($rows as $r) $byId[(int)$r['id']]=$r;
        $visible=Container::get(AtakOperatorPersonnelService::class)->forUsers($tenant,array_keys($byId),OrgVisibilityCapabilities::fromGate());
        $allowed=[];
        foreach ($visible as $id=>$r) if ($r['state']==='visible') $allowed[]=$id;
        $names=(new MemberNameService())->identitiesFor($tenant,$allowed);
        $out=[];
        foreach ($allowed as $id) {
            $r=$byId[$id]; $v=$visible[$id]; $n=$names[$id]??[];
            $out[]=['userId'=>$id,'label'=>$v['name'],'unitId'=>$v['unit_id'],'values'=>[
                'callsign'=>(string)($n['callsign']??$r['callsign']??''),
                'unit'=>$v['unit_label'],'rank'=>$v['grade_short'],
                'firstname'=>(string)($n['first_name']??''),'lastname'=>(string)($n['last_name']??''),
                'organization'=>(string)($r['tenant_name']??''),
                // Pas de relation « group » ni pays exposée par ce contrat RH : substitution explicite.
                'group'=>'','country'=>'',
            ]];
        }
        return $out;
    }
}
