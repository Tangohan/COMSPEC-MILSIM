<?php
require __DIR__.'/bootstrap.php';
use App\Services\PatchStudio\{Document,Projects,Schema};
use App\Support\Migrations\{MigrationRegistry,SchemaInspector};
$checks=0;
function workspaceCheck(bool $value,string $label): void{global $checks;$checks++;if(!$value)throw new RuntimeException($label);}
$root=dirname(__DIR__,2);$pdo=new PDO('sqlite::memory:');$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
try{(new Projects($pdo))->listing(7,1,false);throw new RuntimeException('empty schema accepted');}catch(PDOException $e){workspaceCheck(Schema::missing($e),'missing schema classified');}
Schema::migrate($pdo,$root);$probe=new SchemaInspector($pdo);
foreach(['projects','versions','shares','assets','asset_refs','releases'] as $suffix)workspaceCheck($probe->tableExists('patch_studio_'.$suffix),'table '.$suffix);
foreach(['kind','preview_hash','preview_revision'] as $column)workspaceCheck($probe->columnExists('patch_studio_projects',$column),'column '.$column);
$doc=json_decode(file_get_contents(__DIR__.'/project.json'),true);$repo=new Projects($pdo);$p=$repo->create(7,1,'SETUP LASER',$doc,'setup');
workspaceCheck($p['kind']==='setup','setup uses existing project storage');workspaceCheck($repo->listing(7,1,false)[0]['kind']==='setup','setup returned by catalogue');
Schema::migrate($pdo,$root);workspaceCheck($repo->find($p['id'],7,1,false)['document']===$doc,'rerunning migration preserves project and history');
workspaceCheck((int)$pdo->query('SELECT COUNT(*) FROM patch_studio_versions')->fetchColumn()===1,'migration does not reset versions');
try{$repo->find($p['id'],8,1,true);throw new RuntimeException('cross tenant setup');}catch(OutOfBoundsException){workspaceCheck(true,'private setup rights');}
workspaceCheck(MigrationRegistry::find($root,'patch_studio_workspace')['file']==='3950_patch_studio_workspace.php','registered in real active queue');
$legacy=patchTestDb();$legacy->exec('ALTER TABLE patch_studio_projects DROP COLUMN preview_revision');Schema::migrate($legacy,$root);workspaceCheck((new SchemaInspector($legacy))->columnExists('patch_studio_projects','preview_revision'),'partially installed schema repaired');
(new ReflectionProperty(\App\Core\Database::class,'pdo'))->setValue(null,$pdo);
workspaceCheck(\App\Core\Container::get(\App\Services\Atak\AtakOperatorPersonnelService::class) instanceof \App\Services\Atak\AtakOperatorPersonnelService,'production personnel service is registered');
echo "PASS $checks workspace backend checks\n";
