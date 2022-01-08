<?php

namespace App\Http\Controllers;

use DB;
use Auth;
use Config;
use Mail;
use Session;
use Javascript;
use App\Models\Training;
use App\Models\ResourceTrigger;
use App\Models\Resource;
use App\Models\TrainingCategory;
use App\Models\ApplicationStatus;
use App\Models\UserInformationTraining;
use App\Models\ApplicationSection;
use App\Http\Requests\PasswordRequest;
use App\Http\Requests\CoordinatesRequest; 
use App\Http\Requests\ShortTrainingProjectRequest;
use App\Http\Requests\ShortTrainingChoiceRequest;
use App\Http\Requests\ShotTrainingFundingRequest;
use App\Repositories\AxeInterface;
use App\Repositories\UserInterface;
use App\Repositories\BlockInterface;
use App\Repositories\SchoolInterface;
use App\Repositories\AccountInterface;
use App\Repositories\CompanyInterface;
use App\Repositories\SessionInterface;
use App\Repositories\ResourceInterface;
use App\Repositories\TrainingInterface;
use App\Repositories\TrainingRepository;
use App\Repositories\ApplicationInterface; 
use App\Repositories\BlockSessionInterface; 
use App\Repositories\ConfirmAccountInterface; 
use App\Repositories\UserInformationInterface;
use App\Repositories\SchoolKnowledgeInterface; 
use App\Repositories\ApplicationSectionInterface;
use App\Repositories\SubscriptionInterface;
use App\Repositories\FundingMethodInterface;
use App\Repositories\ApplicationStatusInterface;
use App\Repositories\FundingEntityInterface; 
use App\Repositories\FundingInterface; 
use App\Repositories\ElementInterface;
use App\Repositories\FundingFrequencyInterface;
use Illuminate\Http\Request;
use App\Services\SMSToUser; 
use App\Mail\SendSMSByMail;


class ShortTrainingController extends Controller
{
    /**
     * @var [AccountInterface]
     */
    protected $schoolInterface;
	protected $accountInterface;
    protected $applicationInterface;
    protected $trainingInterface;
    protected $schoolKnowledgeInterface;
    protected $userInformationInterface;
    protected $blockInterface;
    protected $axeInterface;
    protected $blockSessionInterface; 
    protected $resourceInterface;
    protected $confirmAccountInterface;
    protected $applicationSectionInterface;
    protected $userInterface;
    protected $trainingRepository;
    protected $sessionInterface;
    protected $subscriptionInterface;
    protected $applicationStatusInterface;
    protected $fundingMethodInterface;
    protected $fundingEntityInterface;
    protected $fundingInterface;
    protected $fundingFrequencyInterface;
    protected $elementInterface;


    /**
     *
     * @return void
     */
    public function __construct(SchoolInterface $schoolInterface, TrainingInterface $trainingInterface,
                                AccountInterface $accountInterface, ApplicationInterface $applicationInterface,
                                SchoolKnowledgeInterface $schoolKnowledgeInterface, UserInformationInterface $userInformationInterface,
                                CompanyInterface $companyInterface, BlockInterface $blockInterface,
                                AxeInterface $axeInterface, BlockSessionInterface $blockSessionInterface,
                                ResourceInterface $resourceInterface, ConfirmAccountInterface $confirmAccountInterface,
                                ApplicationSectionInterface $applicationSectionInterface, UserInterface $userInterface,
                                TrainingRepository $trainingRepository, SessionInterface $sessionInterface,
                                SubscriptionInterface $subscriptionInterface, ApplicationStatusInterface $applicationStatusInterface,
                                FundingMethodInterface $fundingMethodInterface, FundingEntityInterface $fundingEntityInterface,
                                FundingInterface $fundingInterface, FundingFrequencyInterface $fundingFrequencyInterface,
                                ElementInterface $elementInterface
                                )
    {
        $this->schoolInterface = $schoolInterface;
        $this->accountInterface = $accountInterface;
        $this->applicationInterface = $applicationInterface;
        $this->trainingInterface = $trainingInterface;
        $this->schoolKnowledgeInterface = $schoolKnowledgeInterface;
        $this->userInformationInterface = $userInformationInterface;
        $this->blockInterface = $blockInterface;
        $this->axeInterface = $axeInterface;
        $this->blockSessionInterface = $blockSessionInterface;
        $this->resourceInterface = $resourceInterface;
        $this->confirmAccountInterface = $confirmAccountInterface;
        $this->applicationSectionInterface = $applicationSectionInterface;
        $this->userInterface = $userInterface;
        $this->trainingRepository = $trainingRepository;
        $this->sessionInterface = $sessionInterface;
        $this->subscriptionInterface = $subscriptionInterface;
        $this->applicationStatusInterface = $applicationStatusInterface;
        $this->fundingMethodInterface = $fundingMethodInterface;
        $this->fundingEntityInterface = $fundingEntityInterface;
        $this->fundingInterface = $fundingInterface;
        $this->fundingFrequencyInterface = $fundingFrequencyInterface;
        $this->elementInterface = $elementInterface;


        
    }
    
    
    /**
     * @param mixed $verify_email_token
     * 
     * @return [User]
     */
    public function shortTrainingCreatePassword($school, $application_order, $verify_email_token)
    {

        \JavaScript::put([
            'projet_url' => Config::get('custom.projet_url'),
        ]);

        if($verify_email_token) {
        
            // Je fai une recherche de l'utilisateur par rapport au token
            $user = $this->userInterface->verifyEmailToken($verify_email_token);
        
            if($user) {
                
                return view('shortTraining.shortTrainingCreatePassword', compact('user'));
            } else {
                // Si aucun résultat n'a été trouvé
                return redirect()->route('register')->with('registerError', 'Votre inscription n\'a pas été
                                            validée, veullez recommencer.');
            }

        } else {
            // Message envoyé si le token n'existe pas
            return redirect()->route('register')->with('registerError', 'Votre inscription n\'a pas été
                                        validée, veuillez recommencer.');
        }              
        
    }

    /**
     * @param mixed $school
     * @param PasswordRequest $request
     * 
     * @return [type]
     */
    public function storeShortTrainingPassword($school, PasswordRequest $request)
    {
        
        //A revoir cette partie
		$user = $this->accountInterface->createPassword($request);
		
		$application = $this->applicationInterface->getById($user->application_id);
       
        DB::beginTransaction();

			try {

                //On vérifie si le status mon account est déjà validé.
                $statusAccountIsValidate = $this->applicationStatusInterface
                                                ->checkValidateStatus($application->id, 
                                                ApplicationStatus::where("slug", "=", "account")->first()->id);

                if (!$statusAccountIsValidate) {                                    
                    // On fait l'enregistrement dans la table application_status_validation
                    $this->applicationInterface->saveApplicationStatusValidation($user->id, $application->id, 
                                                ApplicationStatus::where("slug", "=", "account")->first()->id);
                }

                // Mise à jour de application_status_id dans la table application
                $this->applicationInterface->updateApplicationStatus($application->id,
                                            ApplicationStatus::where("slug", "=", "infos")->first()->id);

                // Mise à jour de application_section_id dans la table application
                $this->applicationInterface->updateApplicationSession($application->id, 
                                            ApplicationSection::where("slug", "=", "my_details")->first()->id);

                //On vérifie si l'utilisateur existe
                if($user){
                
                    // On connecte l'utilisateur avant de faire la redirection
                    Auth::login($user);
                
                } else {
                    throw new CustomException;
                }

                DB::commit();

                return redirect()->route('shortTrainingInfos', [$school, $application->application_order, $user->verify_email_token]);

			} catch (\Throwable $th) {
					DB::rollback();
			}
        
    }
    

    /**
     * @param mixed $school
     * @param PasswordRequest $request
     * 
     * @return [type]
     */
    public function shortTrainingInfos($school, $application_order, $verify_email_token)
    {

        /**------------- */
        // $myResources = Resource::select('id', 'path')->get();

        // foreach ($myResources as  $myResource) {
        //     DB::table("resources")
        //         ->where('id', '=', $myResource->id)
        //         ->update([
        //             'iframe_path' => getIframePath($myResource->path)
        //         ]);
            
        // }

        // dd("OK");

        
        /**------------- */

        if($verify_email_token) {

            // if usurpation is active
		    if(Auth::user()->isUsurping()) {
                // retrieve usurped user
                $user = $this->userInterface->getById(Session::get('usurpedId'));

            } else {
                // return auth user
                $user = Auth::user();
            }

            //Je recherche l'école
            $school = $this->schoolInterface->getBySlug($school);

            //Recherche de comment avez-vous connu l'école
            $schoolKnowledges =  $this->schoolKnowledgeInterface->getSchoolAllSchoolKnowledge($school->id);

            //Recherche des informations renseignées par l'utilisateur
            $userInformation =  $this->userInformationInterface->getUserInformation($user->id);

            // On recherche l'application
            $application = $this->applicationInterface->getByProspectId($user->id);

            $axes = $this->axeInterface->all();
            // $blocks = $this->blockInterface->getByAxe();
            // $blockSessions = $this->blockSessionInterface->all();

            //Recherche de toutes les formations courtes de l'école
            $trainings = $this->trainingInterface->getSchoolAllShortTraining($school->id);

            //Rcherche des formations courtes choisie par l'utilisateur
            $userTrainigChoices = $this->applicationInterface->getUserAllShortTrainingChoices($application->id);

            if($userTrainigChoices->count() <= 0) {
                //On met une formation par défaut pour éviter des erreurs
                $userTrainigChoices = Training::where('training_category_id', "=", 
                                                    TrainingCategory::where('slug', '=', 'formation_block')->first()->id)->get();
            }
        
            //Recherche des ressources disponibles pour l'entreprise
            $resources = $this->resourceInterface->getShortTrainingUserResources((int)$userTrainigChoices->first()->id);
            // $resources = $this->resourceInterface->getShortTrainingUserResources(16);

            // dd($resources);
        
            // On vérifie si le code SMS a été déjà validé.
            $checkIfSMSCodeIsValidateBefore =$this->confirmAccountInterface->checkIfSMSCodeIsValidateBefore($user);

            // On vérifie les sections déjà validées
            $sectionCoordinateIsValidate = $this->applicationSectionInterface
                                                ->checkValidateSection($application->id, 
                                                ApplicationSection::where("slug", "=", "my_details")->first()->id);
            $sectionMyProjectIsValidate = $this->applicationSectionInterface
                                                ->checkValidateSection($application->id, 
                                                ApplicationSection::where("slug", "=", "my_wish")->first()->id);
            $sectionMyTrainingIsValidate = $this->applicationSectionInterface
                                                ->checkValidateSection($application->id, 
                                                ApplicationSection::where("slug", "=", "my_training")->first()->id);
            $sectionMyfundingIsValidate = $this->applicationSectionInterface
                                                ->checkValidateSection($application->id, 
                                                ApplicationSection::where("slug", "=", "my_funding")->first()->id);


            //On vérifie les déclencheurs qui ont été enregistrés
            $validate_code_sms = $this->applicationInterface->checkResourceTrigger($application->id,
                                        ResourceTrigger::where("slug", "=", "validate_code_sms")->first()->id);
                                                            
            //On récupère toutes les formations courtes
            $shortTrainings = $this->trainingRepository->getAllShortTrainings();
            
            //On récupère toutes les sessions
            $shortTrainingSessions = $this->sessionInterface->getAllShortTrainingSessions();
            // dd($shortTrainingSessions);
            //On recherche toutes les formations courtes qui ont une session
            $shortTrainingWithSessions = $this->trainingRepository->getAllShortTrainingWithSessions();

            //Je recherche les formations courtes et sessions choisies par l'utilisateur.
            $userShortTrainingsWithoutSessions = $this->applicationInterface->getAllUserShortTrainingsWithoutSession($application->id);
            $userShortTrainingsWithSessions = $this->applicationInterface->getAllUserShortTrainingsWithSession($application->id);
            // Je recherche le nombre de formations choisi par l'utilisateur.
            $userShortTrainingNumber = $this->applicationInterface->getUserShortTrainingNumber($application->id);
    

            //Recherche de tous les choix de formation de l'utilisateur
            $allUserShortTrainingChoices = $this->applicationInterface->getAllUserShortTrainingChoice($application->id);
            // dd($allUserShortTrainingChoices);
            
            // On recherche les slug de toutes les sessions de formations courtes qu'on passe dans le js
            $shortTrainingWithSessionIds = [];
            foreach ($shortTrainingWithSessions as $shortTrainingWithSessions) {
                \array_push($shortTrainingWithSessionIds, $shortTrainingWithSessions->id);
            }
            
            //Permet de rechercher les id de toutes les formations courtes et de les passer en js
            $shortTrainingIds = [];
            foreach ($shortTrainings as $shortTraining) {
                \array_push($shortTrainingIds, $shortTraining->id);
            }

            //Permet de rechercher les slugs de toutes les sessions des formations courtes et de les passer en js
            $shortTrainingSessionIds = [];
            foreach ($shortTrainingSessions as $shortTrainingSession) {
                \array_push($shortTrainingSessionIds, $shortTrainingSession->id);
            }

            //Recherche de toutes les formations et sessions selectionnées par l'utilisateur
            $subscriptions = $this->subscriptionInterface->getApplicationTrainingChoices($application->id);

            // A revoir On recherche tous les financements disponibles et on les cache sur la page
            $fundingMethods = $this->fundingMethodInterface->getAll();
            
            // Recherche des entités de financement
            $fundingEntities = $this->fundingEntityInterface->all();

            //Recherche des methodes de financement en fonction du choix de formation
            $userFundingMethods = $this->subscriptionInterface->getApplicationFundings($application->id);
            
            // Recherche des methodes de financement choisie par l'étudiant
            $funding_methods = $this->fundingMethodInterface->getFundingMethod($application->id);

            //Montant total des formations choisie par l'utilisateur
            $totalPrice = $this->subscriptionInterface->getTotalAmount($application->id);
        
            //Recherche des financements validés par l'utilisateur
            $fundingFundingMethodsAmounts = $this->fundingMethodInterface->getFundingFundingMethodsAmounts($application->id);

            // dd($funding_methods);

            //On place dans ce tableau les id des choix de financement de l'utilisateur
            $fundingMethodsArray = [];
            foreach ($funding_methods as $funding_method) {
                \array_push($fundingMethodsArray, $funding_method->funding_method_id);
            }

            //Recherche des déclencheurs déjà validés
            $validateTriggers = $this->applicationInterface->validateTrigger($application->id);
            // dd($validateTriggers);
            // je convertis $validateTriggers en array pour vérifier si ce trriger a été déjà enregistré.
            $triggers = [];

            foreach ($validateTriggers as $validateTrigger) {
                array_push($triggers, $validateTrigger->resource_trigger_id); 
            }

            //Recherche des fréquences de financement
            
            $once = $this->fundingFrequencyInterface->all()->where('slug', 'once')->first();
            $twice = $this->fundingFrequencyInterface->all()->where('slug', 'twice')->first();

            //On recherche toutes les fréquences de financement
            $fundingFrequencies = $this->fundingFrequencyInterface->all();
           
            // dd($once);
            // dd($triggers);

            // Permet de tranférer ces données au Js
            \JavaScript::put([
                'shortTrainingWithSessionIds' => $shortTrainingWithSessionIds,
                'shortTrainingIds' => $shortTrainingIds,
                'shortTrainingSessionIds' => $shortTrainingSessionIds,
                'userShortTrainingNumber' => $userShortTrainingNumber,
                'allUserShortTrainingChoices' => $allUserShortTrainingChoices,
                'subscriptions' => $subscriptions,
                'userFundingMethods' => $userFundingMethods,
                'totalPrice' => $totalPrice,
                'fundingFundingMethodsAmounts' => $fundingFundingMethodsAmounts,
                'projet_url' => Config::get('custom.projet_url'),
                'resources' => $resources,
                'fundingMethodsArray' => $fundingMethodsArray,
                'once' => $once,
                'twice' => $twice,
            ]);

            // $funding = $this->fundingInterface->getFundingByApplication($application->id);
            // dd($funding);

            return view('shortTraining.shortTrainingInfos', compact('user', 'schoolKnowledges', 'userInformation', 
                                                            'axes', 'trainings', 'userTrainigChoices', 'resources',
                                                            'checkIfSMSCodeIsValidateBefore', 'sectionCoordinateIsValidate',
                                                            'validate_code_sms', 'sectionMyProjectIsValidate',
                                                            'sectionMyTrainingIsValidate', 'sectionMyfundingIsValidate',
                                                            'shortTrainings', 'shortTrainingSessions', 
                                                            'userShortTrainingsWithoutSessions', 'userShortTrainingsWithSessions',
                                                            'userShortTrainingNumber', 'allUserShortTrainingChoices',
                                                            'fundingMethods', 'fundingEntities', 'fundingFundingMethodsAmounts',
                                                            'fundingMethodsArray', 'triggers', 'funding_methods', 'once', 'twice',
                                                            'fundingFrequencies'
                                                        )); 
        } else {
            
            throw new CustomException;
        }
        
    }


    public function shortTrainingBooklet($school, $application_order, $verify_email_token)
    {
        
        if($verify_email_token) {

            // Je fai une recherche de l'utilisateur par rapport au token
            $user = $this->userInterface->verifyEmailToken($verify_email_token);
            
            if($user) {

                //Je recherche l'école
                $school = $this->schoolInterface->getBySlug($school);
                // On recherche l'application
                $application = $this->applicationInterface->getByProspectId($user->id);

                // On recherche tous les trainings dans la base de données
                // $trainings = Training::select('id', 'label')->get();
                //Recherche de toutes les formations courtes de l'école
                $trainings = $this->trainingInterface->getSchoolAllShortTraining($school->id);
                //Rcherche des formations courtes choisie par l'utilisateur
                $userTrainigChoices = $this->applicationInterface->getUserAllShortTrainingChoices($application->id);

                if($userTrainigChoices->count() <= 0) {
                    //On met une formation par défaut pour éviter des erreurs
                    $userTrainigChoices = Training::where('training_category_id', "=", 
                                                        TrainingCategory::where('slug', '=', 'formation_block')->first()->id)->get();
                }

                //Recherche des ressources disponibles
                $resources = $this->resourceInterface->getShortTrainingUserResources($userTrainigChoices->first()->id);

               //On vérifie les déclencheurs qui ont été enregistrés
                $validate_code_sms = $this->applicationInterface->checkResourceTrigger($application->id,
                ResourceTrigger::where("slug", "=", "validate_code_sms")->first()->id);
                                    
                //On récupère toutes les formations courtes
                $shortTrainings = $this->trainingRepository->getAllShortTrainings();
                //On récupère toutes les sessions
                $shortTrainingSessions = $this->sessionInterface->getAllShortTrainingSessions();
                // dd($shortTrainingSessions);
                //On recherche toutes les formations courtes qui ont une session
                $shortTrainingWithSessions = $this->trainingRepository->getAllShortTrainingWithSessions();

                //Je recherche les formations courtes et sessions choisies par l'utilisateur.
                $userShortTrainingsWithoutSessions = $this->applicationInterface->getAllUserShortTrainingsWithoutSession($application->id);
                $userShortTrainingsWithSessions = $this->applicationInterface->getAllUserShortTrainingsWithSession($application->id);

                // On recherche les slug de toutes les sessions de formations courtes qu'on passe dans le js
                $shortTrainingWithSessionIds = [];
                foreach ($shortTrainingWithSessions as $shortTrainingWithSessions) {
                    \array_push($shortTrainingWithSessionIds, $shortTrainingWithSessions->id);
                }

                //Permet de rechercher les id de toutes les formations courtes et de les passer en js
                $shortTrainingIds = [];
                foreach ($shortTrainings as $shortTraining) {
                    \array_push($shortTrainingIds, $shortTraining->id);
                }

                //Permet de rechercher les slugs de toutes les sessions des formations courtes et de les passer en js
                $shortTrainingSessionIds = [];
                foreach ($shortTrainingSessions as $shortTrainingSession) {
                    \array_push($shortTrainingSessionIds, $shortTrainingSession->id);
                }
        
				// On recherche les resources selon la première formation choisie par l'utilisateur
				$firstTrainingBrochureChoice = $this->trainingInterface->getFirstTrainingChoice($user->id, 1);
				$firstTrainingTeaserChoice = $this->trainingInterface->getFirstTrainingChoice($user->id, 2);
				$firstTrainingPresentationChoice = $this->trainingInterface->getFirstTrainingChoice($user->id, 3);
				$firstTrainingPlanningChoice = $this->trainingInterface->getFirstTrainingChoice($user->id, 4);
				$firstTrainingBenefitChoice = $this->trainingInterface->getFirstTrainingChoice($user->id, 5);
				$firstTrainingProfileChoice = $this->trainingInterface->getFirstTrainingChoice($user->id, 6);
				$firstTrainingOutilChoice = $this->trainingInterface->getFirstTrainingChoice($user->id, 7);
				$firstTrainingOrganisationChoice = $this->trainingInterface->getFirstTrainingChoice($user->id, 8);
				$firstTrainingMarketChoice = $this->trainingInterface->getFirstTrainingChoice($user->id, 9);
				$firstTrainingTestimonyChoice = $this->trainingInterface->getFirstTrainingChoice($user->id, 10);
				$firstTrainingFoundingChoice = $this->trainingInterface->getFirstTrainingChoice($user->id, 11);

				// $application = $this->applicationInterface->getByProspectId($user->id);

				$application_order = $application->application_order;
				$application_section_id = $application->application_section_id;
				$application_status_id = $application->application_status_id;
				$application_id = $application->id;
				$training_id = $application->training_id;
				// $training_idea = $userInformation->training_idea;

				$organisation = $this->resourceInterface->getResource($training_id, 8);
				$market = $this->resourceInterface->getResource($training_id, 9);
				$testimony = $this->resourceInterface->getResource($training_id, 10);
                $founding = $this->resourceInterface->getResource($training_id, 11);
                $rib = $this->resourceInterface->getResource($training_id, 12);
                
				// On vérifie les sections validées
				$sectionCoordinateIsValidate = $this->applicationSectionInterface->checkValidateSection($application_id, 1);
				$sectionMySituationIsValidate = $this->applicationSectionInterface->checkValidateSection($application_id, 2);
				$sectionMyWishIsValidate = $this->applicationSectionInterface->checkValidateSection($application_id, 3);
				$sectionMyTrainingIsValidate = $this->applicationSectionInterface->checkValidateSection($application_id, 4);
				$sectionRecMySituationIsValidate = $this->applicationSectionInterface->checkValidateSection($application_id, 5);
				$sectionMyFundingIsValidate = $this->applicationSectionInterface->checkValidateSection($application_id, 6);


                // On recherche tous les trainings que l'utilisateur a cochés
                $userInformationTrainings = UserInformationTraining::select("trainings.label")
                    ->join("trainings", "user_information_training.training_id", "=", "trainings.id")
                    ->join("user_informations", "user_information_training.user_information_id", "=", "user_informations.id" )
                    ->where("user_informations.user_id", "=", $user->id)
                    ->get();

            //Recherche des déclencheurs déjà validés
            $validateTriggers = $this->applicationInterface->validateTrigger($application->id);

            // je convertis $validateTriggers en array pour vérifier si ce trriger a été déjà enregistré.
            $triggers = [];

            foreach ($validateTriggers as $validateTrigger) {
                array_push($triggers, $validateTrigger->resource_trigger_id); 
            }

            
            // Permet de tranférer ces données au Js
            \JavaScript::put([
                'shortTrainingWithSessionIds' => $shortTrainingWithSessionIds,
                'shortTrainingIds' => $shortTrainingIds,
                'shortTrainingSessionIds' => $shortTrainingSessionIds,
                // 'userShortTrainingNumber' => $userShortTrainingNumber,
                // 'allUserShortTrainingChoices' => $allUserShortTrainingChoices,
                // 'subscriptions' => $subscriptions,
                // 'userFundingMethods' => $userFundingMethods,
                // 'totalPrice' => $totalPrice,
                'projet_url' => Config::get('custom.projet_url'),
                'resources' => $resources
            ]);

        return view('shortTraining.shortTrainingBooklet', compact('user', 'trainings', 'userInformationTrainings',
                                        'firstTrainingBrochureChoice', 'firstTrainingTeaserChoice', 'firstTrainingPresentationChoice',
                                        'firstTrainingPlanningChoice', 'firstTrainingBenefitChoice', 'firstTrainingProfileChoice',
                                        'firstTrainingOutilChoice', 'firstTrainingOrganisationChoice', 'firstTrainingMarketChoice',
                                        'firstTrainingTestimonyChoice', 'firstTrainingFoundingChoice', 'organisation', 'market',
                                        'testimony', 'founding', 'application_section_id', 'sectionCoordinateIsValidate',
                                        'sectionMySituationIsValidate', 'sectionMyWishIsValidate', 'sectionMyTrainingIsValidate',
                                        'rib', 'userTrainigChoices', 'resources', 'triggers'));
            } else {
                // Si aucun résultat n'a été trouvé
                return redirect()->route('register')->with('registerError', 'Votre inscription n\'a pas été
                                            validée, veullez recommencer.');
            }

        } else {
            // Message envoyé si le token n'existe pas
            return redirect()->route('register')->with('registerError', 'Votre inscription n\'a pas été
                                        validée, veuillez recommencer.');
        }              
    }

    /**
     * @param mixed $school
     * @param CoordinatesRequest $request
     * 
     * @return [type]
     */
    public function storeShortTrainingCoordinates($school, CoordinatesRequest $request)
    {

        // if usurpation is active
        if(Auth::user()->isUsurping()) {
            // retrieve usurped user
            $user = $this->userInterface->getById(Session::get('usurpedId'));

        } else {
            // return auth user
            $user = Auth::user();
        }

        //On utilise une transaction pour cette opération
		DB::beginTransaction();

        try {
            // Je génère un token de confirmation de compte de 6 chiffres
            $six_digit_confirm_account = mt_rand(100000, 999999);
            
            //On persiste les informations dans la tables users
            $this->userInterface->saveMyCoordinates($user, $request);
            
            //On persiste les informations dans la tables user_informations
            $this->userInformationInterface->saveMyCoordinates($user, $request);
            
            //Je recherche l'application
            $application = $this->applicationInterface->getByProspectId($user->id);
    
            //On met à jour la table application_section_validations
            // On vérifie d'abord si la section mes coordonnées est déjà validée
            $sectionCoordinateIsValidate = $this->applicationSectionInterface
                                                ->checkValidateSection($application->id, 
                                                ApplicationSection::where("slug", "=", "my_details")->first()->id);
            if(!$sectionCoordinateIsValidate) {
                $this->applicationSectionInterface->saveApplicationSectionValidation($user->id, $application->id,
                ApplicationSection::where("slug", "=", "my_details")->first()->id);
            }
            
            // On vérifie d'abord si un code a été déja généré pour l'utilisateur
            $confirmAccountCode = $this->confirmAccountInterface->isCodeGenerateBefore($user);
    
            // Si le code a été déjà ggénéré, on fait un update, sinon on crée une nouvelle instance
            if($confirmAccountCode) {
                $this->confirmAccountInterface->updateCode($user, $confirmAccountCode, $six_digit_confirm_account);
            } else {
                $this->confirmAccountInterface->saveCode($user, $six_digit_confirm_account);
            }
    
            // On vérifie si le code a été déjà validé.
            //Si oui on envoie un SMS sinon on appelle plus la function sendSMSToUser
            $checkIfSMSCodeIsValidateBefore =$this->confirmAccountInterface->checkIfSMSCodeIsValidateBefore($user);
            
            if($checkIfSMSCodeIsValidateBefore->is_valid_code !== 1){

                // On envoie un SMS à l'utilisateur en faisant appel à la class SMSToUser
                $sMSToUser = new SMSToUser($request->phone_number, $six_digit_confirm_account);
                $sMSToUser->send();

                //on envoie le code par mail aussi
                $userEmail =  $user->email;
                $url = Config::get('custom.projet_url') . $school . "/shortTrainingInfos/". $application->application_order . "/" . $user->verify_email_token;
                
                // $sendSMSByMail = new SendSMSByMail($userEmail, $url, $six_digit_confirm_account);
                
                // //on envoie du mail
                // Mail::to($userEmail)
                //         ->send($sendSMSByMail);

            }
            
            //On vérifie si la section mes coordonnées a été déjà enregistrée
            $detail_form_validation = $this->applicationInterface->checkDetailFormIsValidateBefore($application->id);
            
            if($detail_form_validation) {

                // Si mes coordonnées déjà validées
                $this->applicationInterface->updateDetailForm($application->id);

            } else {
                // Sinon on crée une nouvelle instance
                $this->applicationInterface->saveDetailForm($application->id);
        
            }

        DB::commit(); 
        
            return redirect()->route('shortTrainingInfos', [$school, $application->application_order, $user->verify_email_token]);
        
        } catch (\Throwable $th) {
            DB::rollback();
        }
    }

    /**
     * @param mixed $school
     * @param ShortTrainingProjectRequest $request
     * 
     * @return [type]
     */
    public function storeShortTrainingProject($school, ShortTrainingProjectRequest $request)
    {
        
        // if usurpation is active
        if(Auth::user()->isUsurping()) {
            // retrieve usurped user
            $user = $this->userInterface->getById(Session::get('usurpedId'));

        } else {
            // return auth user
            $user = Auth::user();
        }

        DB::beginTransaction();

        try {


            //On fait l'enregistrement de de la motivation du prospect
            $this->userInformationInterface->saveMotivation($user->id, $request);

            //Je recherche l'application
            $application = $this->applicationInterface->getByProspectId($user->id);

            // Mise à jour de application_status_id dans la table applications
            $this->applicationInterface->updateApplicationStatus($application->id, 3);

            //on fait une mise à jour de la section courante
            $this->applicationInterface->updateApplicationSession($application->id,
                                        ApplicationSection::where("slug", "=", "my_training")->first()->id);
            
            // On vérifie si cette section mon projet est déjà validée
            $sectionMyProjectIsValidate = $this->applicationSectionInterface
                                                ->checkValidateSection($application->id, 
                                                ApplicationSection::where("slug", "=", "my_wish")->first()->id);
            if (!$sectionMyProjectIsValidate) {
                // On met à jour la table application_section_validations
                $this->applicationSectionInterface->saveApplicationSectionValidation($user->id, $application->id,
                                                    ApplicationSection::where("slug", "=", "my_wish")->first()->id);
            }  

            DB::commit();
        
            return response()->json([
                'motivation' => "validate",
                
            ]);

        } catch (\Throwable $th) {
            DB::rollback();
        }
    
    }

    /**
     * @param mixed $school
     * @param ShortTrainingProjectRequest $request
     * 
     * @return [type]
     */
    public function updadingUserTrainingChoice($school, ShortTrainingChoiceRequest $request)
    {
        //Je récupère les choix de formations de l'utilisateur
        $shortTrainings = $request->shortTrainings;
        $shortTraining_sessions = $request->shortTraining_sessions;

        // if usurpation is active
        if(Auth::user()->isUsurping()) {
            // retrieve usurped user
            $user = $this->userInterface->getById(Session::get('usurpedId'));

        } else {
            // return auth user
            $user = Auth::user();
        }

        //Je recherche l'application
        $application = $this->applicationInterface->getByProspectId($user->id);

        //On efface tous les  choix de formation de l'utilisateur
        $this->applicationInterface->deleteAllUserTrainingChoice($application->id);

        //Je fais un enregistrement de toutes les formations courtes de l'utilisateur.
        if(!empty($shortTrainings)) {
            $this->applicationInterface->saveUserShortTrainingChoice($application->id, $shortTrainings);
        }

        //Je fais un enregistrement de toutes les sessions.
        if(!empty($shortTraining_sessions)) {
            $this->applicationInterface->saveUserShortTrainingSessionChoice($application->id, $shortTraining_sessions);
        }

        //On recherche le nombre de formations et de sessions selectionnées
        $userShortTrainingNumber = $this->applicationInterface->getUserShortTrainingNumber($application->id);

        //Recherche de tous les choix de formation de l'utilisateur
        $allUserShortTrainingChoices = $this->applicationInterface->getAllUserShortTrainingChoice($application->id);

        return response()->json([
            'userShortTrainingNumber' => $userShortTrainingNumber,
            'allUserShortTrainingChoices' =>  $allUserShortTrainingChoices,
            'motivation' => "validate",
        ]);
    
    }

    /**
     * @return [type]
     */
    public function linkToLongTraining($school)
    {
        //On recherche l'utilisateur
        // if usurpation is active
        if(Auth::user()->isUsurping()) {
            // retrieve usurped user
            $user = $this->userInterface->getById(Session::get('usurpedId'));

        } else {
            // return auth user
            $user = Auth::user();
        }

        //Je recherche l'application
        $application = $this->applicationInterface->getByProspectId($user->id);
    
        //Je change la catégorie de l'application
        $this->applicationInterface->changeApplicationCategory($application->id, 
                                    TrainingCategory::where('slug', '=', 'formation_longue')->first()->id);

        //Je recherche le userinforamtion
        $userinformation = $this->userInformationInterface->getUserInformation($user->id);

        //On choisit une formation par défaut pour l'utilisateur
        //Afin d'éviter des erreurs
        $training = Training::where('training_category_id', "=", 
                                                    TrainingCategory::where('slug', '=', 'formation_longue')->first()->id)->first();

        //J'enregistre les informations dans la table user_information_training
        $userInformationTraining = new UserInformationTraining;

        $userInformationTraining->training_id = $training->id;
        $userInformationTraining->training_category_id = $training->training_category_id;
        $userInformationTraining->user_information_id = $userinformation->id;
        $userInformationTraining->save();
        
        return  redirect($user->school->slug . '/myInfos/'. $application->application_order . '/' . $user->verify_email_token);
    }



    /**
     * @param mixed $school
     * @param ShortTrainingChoiceRequest $request
     * 
     * @return [type]
     */
    public function storeShortTrainingSelect($school, ShortTrainingChoiceRequest $request)
    {

        //Je récupère les choix de formations de l'utilisateur
        $shortTrainings = $request->shortTrainings;
        $shortTraining_sessions = $request->shortTraining_sessions;
        

        // if usurpation is active
        if(Auth::user()->isUsurping()) {
            // retrieve usurped user
            $user = $this->userInterface->getById(Session::get('usurpedId'));

        } else {
            // return auth user
            $user = Auth::user();
        }

        // Je recherche l'application
        $application = $this->applicationInterface->getByProspectId($user->id);

        // On vérifie si l'utilisateur a déjà validé cette application et on annule tous les financements
        $funding = $this->fundingInterface->getFundingByApplication($application->id);

        //Si il y a un résultat, on efface tout le financement lié à cette application
        if ($funding) {

            // Je supprime les entrées de la table element_funding_fundind_method
            // si l'utilisateur a déjà passé ce niveau
            $this->elementInterface->deleteElementFundingMethodByApplicationId($application->id);
            
            //on efface les données de la table funding_funding_method d'abord
            $this->fundingMethodInterface->removeFundingMethods($funding->id);
            //on efface ensuite les données dans la table funding
            $this->fundingInterface->remove($funding->id);
        }

        //On efface toutes les selections qui correspondent à cette application
        $this->subscriptionInterface->deleteApplicationTraining($application->id);

        if (count($shortTrainings) > 0) {
        
            $this->subscriptionInterface->saveUserTraining($application->id, $shortTrainings);
        }

        if (count($shortTraining_sessions) > 0) {
            $this->subscriptionInterface->saveUserSession($application->id, $shortTraining_sessions);
            
        }

        //On vérifie si cette section my select est déjà validée 
        $sectionMyTrainingIsValidate = $this->applicationSectionInterface
                                            ->checkValidateSection($application->id, 
                                            ApplicationSection::where("slug", "=", "my_training")->first()->id);
        
        if (!$sectionMyTrainingIsValidate) {
            // On met à jour la table application_section_validations
            $this->applicationSectionInterface->saveApplicationSectionValidation($user->id, $application->id,
                                                ApplicationSection::where("slug", "=", "my_training")->first()->id);
        }
        

        //On vérifie si le status mon projet est déjà validé.
        $statusProjectIsValidate = $this->applicationStatusInterface
                                        ->checkValidateStatus($application->id, 
                                        ApplicationStatus::where("slug", "=", "project")->first()->id);
        if (!$statusProjectIsValidate) {                                    
            // On fait l'enregistrement dans la table application_status
            $this->applicationInterface->saveApplicationStatusValidation($user->id, $application->id, 
                                        ApplicationStatus::where("slug", "=", "project")->first()->id);
        }

        //On fait une mise à jour de la table application
        // Mise à jour de application_status_id dans la table application
        $this->applicationInterface->updateApplicationStatus($application->id,
                                    ApplicationStatus::where("slug", "=", "funding")->first()->id);

        // Mise à jour de application_section_id dans la table application
        $this->applicationInterface->updateApplicationSession($application->id, 
                                    ApplicationSection::where("slug", "=", "my_funding")->first()->id);

        //Recherche de toutes les formations et sessions selectionnées par l'utilisateur
        $subscriptions = $this->subscriptionInterface->getApplicationTrainingChoices($application->id);

        //Recherche de toutes les formations et sessions selectionnées par l'utilisateur
        $fundingMethods = $this->subscriptionInterface->getApplicationFundings($application->id);

        //Montant total des formations choisie par l'utilisateur
        $totalPrice = $this->subscriptionInterface->getTotalAmount($application->id);

        // Recherche des methodes de financement choisie par l'étudiant
        $funding_methods = $this->fundingMethodInterface->getFundingMethod($application->id);

        // On recherche tous les financements disponibles et on les cache sur la page
        $allfundingMethods = $this->fundingMethodInterface->getAll();

        //Recherche des financements validés par l'utilisateur s'il a déjà validé cette partie
        $fundingFundingMethodsAmounts = $this->fundingMethodInterface->getFundingFundingMethodsAmounts($application->id);

        //On place dans ce tableau les id des choix de financement de l'utilisateur
        $fundingMethodsArray = [];
        foreach ($funding_methods as $funding_method) {
            \array_push($fundingMethodsArray, $funding_method->funding_method_id);
        }

        
        //On fait les enregistrements dans la table subscription
        return response()->json([
            'result' => "validate",
            'subscriptions' => $subscriptions,
            'fundingMethods' => $fundingMethods,
            'totalPrice' => $totalPrice,
            'fundingMethodsArray' =>  $fundingMethodsArray,
            'allfundingMethods' =>  $allfundingMethods,
            'fundingFundingMethodsAmounts' =>  $application->id
        ]);
    
    }

    /**
     * Undocumented function
     *
     * @param [type] $school
     * @param ShotTrainingFundingRequest $request
     * @return void
     */
    public function storeShortTrainingFunding($school, ShotTrainingFundingRequest $request) {

        // if usurpation is active
        if(Auth::user()->isUsurping()) {
            // retrieve usurped user
            $user = $this->userInterface->getById(Session::get('usurpedId'));

        } else {
            // return auth user
            $user = Auth::user();
        }
    
        // Je recherche l'application
        $application = $this->applicationInterface->getByProspectId($user->id);

        // On vérifie si l'utilisateur a déjà validé cette application et on annule tous les financements
        $funding = $this->fundingInterface->getFundingByApplication($application->id);
        //    dd($funding);

        DB::beginTransaction();

        try {
            //Si il y a un résultat, on efface tout le financement lié à cette application
            if ($funding) {

                // On recherche les methodes de financement choisies par l'utilisateur
                $old_funding_methods = $this->fundingMethodInterface->getFundingFundingMethodByFunding($funding->id);
                
                //S'il y a des methodes de financement préalablement choisies, on les supprime
                if ($old_funding_methods) {
                    
                    $this->elementInterface->deleteElementFundingMethod($old_funding_methods);
                }

                //on efface les données de la table funding_funding_method d'abord
                $this->fundingMethodInterface->removeFundingMethods($funding->id);
                //on efface ensuite les données dans la table funding
                $this->fundingInterface->remove($funding->id);
            }

            // Il va falloir vérifier si l'application a été déjà validé afin de faire un update
            $newFunding = $this->fundingInterface->saveShortTrainingFunding($application->id, $request);

            $this->fundingMethodInterface->saveShortTrainingFunding($newFunding->id, $request);

            // On recherche le financement de l'application
            $funding = $this->fundingInterface->getFundingByApplication($application->id);
        
            // Recherche des methodes de financement choisie par l'utilisateur
            $funding_methods = $this->fundingMethodInterface->getFundingFundingMethodByFunding($funding->id);

            //Je fais l'enregistrement des éléments attendus dans la table element_funding_funding_method
            $this->elementInterface->saveElementFundingMethod($funding_methods, $application->id);


            // Mise à jour de application_status_id dans la table applications
            $this->applicationInterface->updateApplicationStatus($application->id, 5);

            DB::commit();

            // On redirige vers la page de recapitulatif
            return redirect()->route('shortTrainingSummaryRegistration', $school);
            
        } catch (\Throwable $th) {
            DB::rollback();
        }

    }

}
