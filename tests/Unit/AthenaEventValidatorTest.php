<?php
declare(strict_types=1);
namespace Tests\Unit;
use App\Services\Tactical\AthenaEventValidator;
use PHPUnit\Framework\TestCase;
final class AthenaEventValidatorTest extends TestCase
{
 public function testValidEventAndStateClassification():void{$e=['schema'=>'athena.event.v1','event_id'=>'evt_12345678','type'=>'position.updated','timestamp'=>'2026-09-04T17:12:31.284Z','source'=>['terminal_id'=>'ATAK-01','source_type'=>'arma3'],'context'=>['world'=>'Altis'],'payload'=>['entity_id'=>'u1']];self::assertSame([],AthenaEventValidator::errors($e));self::assertTrue(AthenaEventValidator::isState($e['type']));self::assertFalse(AthenaEventValidator::isState('marker.created'));}
 public function testRejectsMalformedEnvelope():void{$errors=AthenaEventValidator::errors(['schema'=>'bad','event_id'=>'x','type'=>'BAD TYPE','timestamp'=>'today','source'=>[],'payload'=>'<script>']);self::assertContains('unsupported_schema',$errors);self::assertContains('invalid_event_id',$errors);self::assertContains('invalid_type',$errors);self::assertContains('invalid_timestamp',$errors);self::assertContains('invalid_source',$errors);self::assertContains('invalid_payload',$errors);}
}
