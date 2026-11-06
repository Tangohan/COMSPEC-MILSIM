<?php
// In-memory billing fixtures; the application always uses its existing repositories/services.
function patchTestPlan(string $slug='pro_plus', ?bool $enabled=null, string $status='active', array $settings=[]): void
{
    $tenant=['id'=>7,'plan_slug'=>$slug,'subscription_status'=>$status,'settings'=>json_encode($settings)];
    $tenants=new class($tenant) extends \App\Repositories\TenantRepository {
        public function __construct(private array $fixture) {}
        public function findById(int $id): ?array { return $id===7?$this->fixture:null; }
    };
    $plans=new class($enabled) extends \App\Repositories\SubscriptionPlanRepository {
        public function __construct(private ?bool $enabled) {}
        public function findBySlug(string $slug): ?array { return ['slug'=>$slug,'features_json'=>json_encode($this->enabled===null?[]:['patch_studio'=>$this->enabled])]; }
    };
    $gate=(new ReflectionClass(\App\Services\Platform\FeatureGateService::class))->newInstanceWithoutConstructor();
    (new ReflectionProperty($gate,'tenantRepository'))->setValue($gate,$tenants);
    (new ReflectionProperty($gate,'planRepository'))->setValue($gate,$plans);
    $instances=(new ReflectionProperty(\App\Core\Container::class,'instances'));
    $bound=$instances->getValue();
    $bound[\App\Repositories\TenantRepository::class]=$tenants;
    $bound[\App\Services\Platform\FeatureGateService::class]=$gate;
    $instances->setValue(null,$bound);
}
