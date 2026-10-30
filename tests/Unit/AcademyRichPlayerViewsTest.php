<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Response;
use App\Services\Academy\AcademyCurriculum;
use PHPUnit\Framework\TestCase;

final class AcademyRichPlayerViewsTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = ['_csrf_token'=>str_repeat('a',64), 'user_id'=>1];
        $GLOBALS['__app_locale']='fr';
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['__app_locale']);
    }

    private function bilingual(string $text): array
    {
        return ['fr'=>$text, 'en'=>$text];
    }

    private function course(): array
    {
        $course=AcademyCurriculum::demo();
        $course['key']='jtac-licence-ath-academy';
        $course['built_in']=true;
        $course['demo']=false;
        $course['license']=$this->bilingual('Licence de pratique de simulation');
        $course['duration_minutes']=90;
        $chapter=$course['chapters'][0];
        $question=['question'=>$this->bilingual('Question distincte'), 'options'=>[$this->bilingual('Premier choix'),$this->bilingual('Deuxième choix'),$this->bilingual('Troisième choix')], 'answer'=>1, 'feedback'=>$this->bilingual('Explication réservée au corrigé')];
        $chapter['guided']=$question+['brief'=>$this->bilingual('Cas guidé avec des faits nouveaux'), 'hint'=>$this->bilingual('Conseil guidé uniquement')];
        $chapter['simulation']=array_replace($question,['question'=>$this->bilingual('Question autonome distincte'), 'brief'=>$this->bilingual('Cas autonome sans conseil')]);
        $chapter['assessment']=['seconds'=>900, 'pass_percent'=>80, 'instructions'=>$this->bilingual('Choisissez vos réponses.'), 'questions'=>[$question,$question]];
        $chapter['debrief']=['prompt'=>$this->bilingual('Comparez votre décision aux faits.'), 'criteria'=>[$this->bilingual('Proposer une amélioration vérifiable')]];
        $chapter['validation']=['brief'=>$this->bilingual('Lire les preuves avant de valider.'), 'criteria'=>[$this->bilingual('Incertitude signalée sans ambiguïté')]];
        $chapter['resources']=[['title'=>$this->bilingual('Fiche intégrée'), 'type'=>'document', 'body'=>$this->bilingual('<script>texte sécurisé</script>')]];
        $course['chapters']=[$chapter];
        return $course;
    }

    private function render(string $space, ?array $state=null, array $extra=[]): string
    {
        $course=$this->course();
        return Response::view('academy.space',$extra+[
            'space'=>$space, 'staff'=>true, 'states'=>$state?[$course['key']=>$state]:[],
            'courses'=>[$course], 'selected'=>$course, 'state'=>$state,
            'learners'=>[], 'notice'=>'',
        ])->body();
    }

    public function testPublishedModulePreviewAndUnavailableStorageDoNotOfferDemoOrWritableEnrolment(): void
    {
        $body=$this->render('classroom',null,['academyStorageReady'=>false]);
        self::assertStringContainsString('Cursus publié',$body);
        self::assertStringNotContainsString('Cursus de démonstration</p>',$body);
        self::assertStringContainsString('90 min',$body);
        self::assertStringContainsString('disabled>Commencer ce cursus',$body);
        self::assertStringContainsString('Validation des acquis',$body);
    }

    public function testGuidedAndAutonomousActivitiesRenderDifferentCases(): void
    {
        $state=['chapter'=>0,'stage'=>2,'snapshot'=>$this->course()];
        $guided=$this->render('laboratory',$state);
        self::assertStringContainsString('Cas guidé avec des faits nouveaux',$guided);
        self::assertStringContainsString('Conseil guidé uniquement',$guided);
        self::assertStringNotContainsString('Question autonome distincte',$guided);
        $state['stage']=3;
        $simulation=$this->render('simulators',$state);
        self::assertStringContainsString('Question autonome distincte',$simulation);
        self::assertStringNotContainsString('Conseil guidé uniquement',$simulation);
    }

    public function testMultiQuestionExamRendersConfiguredDurationWithoutCorrectAnswersOrFeedback(): void
    {
        $state=['chapter'=>0,'stage'=>4,'snapshot'=>$this->course(),'exam'=>['deadline'=>time()+900,'token'=>'server-exam-token']];
        $body=$this->render('exams',$state);
        self::assertStringContainsString('name="answers[0]"',$body);
        self::assertStringContainsString('name="answers[1]"',$body);
        self::assertStringContainsString('15 min',$body);
        self::assertStringContainsString('80 %',$body);
        self::assertStringContainsString('server-exam-token',$body);
        self::assertStringNotContainsString('Explication réservée au corrigé',$body);
        self::assertStringNotContainsString('data-answer',$body);
        self::assertStringNotContainsString('"answer":',$body);
    }

    public function testDebriefAndInstructorSeeSubmittedEvidenceAndValidationRubric(): void
    {
        $course=$this->course();
        $state=['chapter'=>0,'stage'=>5,'snapshot'=>$course,'attempts'=>[['chapter'=>0,'score'=>100,'correct_count'=>2,'question_count'=>2,'at'=>time(),'results'=>[['selected'=>1,'correct'=>true],['selected'=>1,'correct'=>true]]]]];
        $body=$this->render('classroom',$state);
        self::assertStringContainsString('100 %',$body);
        self::assertStringContainsString('Explication réservée au corrigé',$body);
        self::assertStringContainsString('Proposer une amélioration vérifiable',$body);
        $state['stage']=6;
        $state['debriefs']=[0=>'Débriefing conservé par le serveur.'];
        $review=$this->render('instructor',null,['learners'=>[['user_id'=>2,'course_key'=>$course['key'],'state'=>$state]]]);
        self::assertStringContainsString('Débriefing conservé par le serveur.',$review);
        self::assertStringContainsString('Incertitude signalée sans ambiguïté',$review);
        self::assertStringContainsString('name="learner" value="2"',$review);
    }

    public function testInlineLibraryIsAvailableWithoutAnEnrolmentAndEscapesAuthoredContent(): void
    {
        $body=$this->render('library');
        self::assertStringContainsString('Fiche intégrée',$body);
        self::assertStringContainsString('&lt;script&gt;texte sécurisé&lt;/script&gt;',$body);
        self::assertStringNotContainsString('<script>texte sécurisé</script>',$body);
    }

    public function testPersistedInternalLicenceAppearsOnlyAfterFinalCompletionInBothLanguages(): void
    {
        $course=$this->course();
        $state=['chapter'=>0,'stage'=>6,'snapshot'=>$course,'completed'=>true,'learner_id'=>1,'license'=>[
            'number'=>'ATH-ACADEMY-JTAC-ABCDEF123456','issued_at'=>time(),'validated_by'=>2,'course_version'=>'1.0','scope'=>'simulation',
        ]];
        foreach(['fr','en'] as $language){
            $GLOBALS['__app_locale']=$language;
            $body=$this->render('record',$state);
            self::assertStringContainsString('ATH-ACADEMY-JTAC-ABCDEF123456',$body);
            self::assertStringContainsString('data-print',$body);
            self::assertStringContainsString($language==='fr'?'qualification JTAC officielle':'official JTAC qualification',$body);
            $completedPlayer=$this->render('classroom',$state);
            self::assertSame(7,substr_count($completedPlayer,'<li class="done"'));
            self::assertStringNotContainsString('aria-current="step"',$completedPlayer);
        }
        unset($state['completed']);
        self::assertStringNotContainsString('ATH-ACADEMY-JTAC-ABCDEF123456',$this->render('record',$state));
    }

    public function testCompletedMaterialsRemainReadableWithoutProgressActionsAndAreHiddenDuringAnExam(): void
    {
        $course=$this->course();
        foreach (['fr','en'] as $language) {
            $GLOBALS['__app_locale']=$language;
            $state=['chapter'=>0,'stage'=>3,'snapshot'=>$course];
            $body=$this->render('classroom',$state);
            self::assertStringContainsString('data-completed-materials',$body);
            self::assertStringContainsString($course['chapters'][0]['theory'][$language],$body);
            self::assertStringContainsString('name="expected_stage" value="3"',$body);
            self::assertStringNotContainsString('/action/advance',$body);
            $state['stage']=4;
            $state['exam']=['deadline'=>time()+900,'token'=>'server-exam-token'];
            $exam=$this->render('exams',$state);
            self::assertStringNotContainsString('data-completed-materials',$exam);
            self::assertStringNotContainsString($course['chapters'][0]['theory'][$language],$exam);
        }
    }
}
