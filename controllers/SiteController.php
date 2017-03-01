<?php
namespace app\controllers;
$path = $_SERVER['DOCUMENT_ROOT'];
include_once $path . '/wp-load.php';
use Yii;
use yii\filters\AccessControl;
use yii\web\Controller;
use yii\filters\VerbFilter;
use app\models\LoginForm;
use app\models\ContactForm;

class SiteController extends Controller
{
    /**
     * @inheritdoc
     */
	public $enableCsrfValidation = false;
    public function behaviors()
    {
        return [
            'access' => [
                'class' => AccessControl::className(),
                'only' => ['logout'],
                'rules' => [
                    [
                        'actions' => ['logout'],
                        'allow' => true,
                        'roles' => ['@'],
                    ],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'logout' => ['post'],
                ],
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public function actions()
    {
        return [
            'error' => [
                'class' => 'yii\web\ErrorAction',
            ],
            'captcha' => [
                'class' => 'yii\captcha\CaptchaAction',
                'fixedVerifyCode' => YII_ENV_TEST ? 'testme' : null,
            ],
        ];
    }

    /**
     * Displays homepage.
     *
     * @return string
     */
    public function actionIndex(){

        return $this->render('index');
    }

	public function actionEndPoint(){
		$body 		= print_r($_GET, true);
		$postdata	= file_get_contents("php://input");
		$request	= json_decode($postdata);
		$filename	= time();
		$fp 		= fopen($filename.'post.json', 'w');
		fwrite($fp, json_encode($request));
		fclose($fp);

		$fp 	= fopen($filename.'get.json', 'w');
		fwrite($fp,$body);
		fclose($fp);

		header('Content-Type: application/json');
		echo json_encode($_POST);


		$request = $_SERVER['SERVER_PROTOCOL'] .' '. $_SERVER['REQUEST_METHOD'] .' '. $_SERVER['REQUEST_URI'] . PHP_EOL;
		foreach (getallheaders() as $key => $value) {
			$request .= trim($key) .': '. trim($value) . PHP_EOL;
		}
		$request .= PHP_EOL . file_get_contents('php://input');

		$fp 	= fopen($filename.'request.json', 'w');
		fwrite($fp,$request);
		fclose($fp);

		die;

		//return $this->render('index');
	}

	public function actionStartPoint(){

		$body 	= array(
			'PropertyID'=> 45678,
			'Property Name' => 'Test Name 2'
		);

		header('Content-Type: application/json');
		echo json_encode($body);
		die;
	}


	public function actionWebHook(){
		$url = 'https://www.workato.com/webhooks/rest/4d8a9d93-0f3a-4bb0-8b15-b7fd99d010b7/property-webhook';
		$body 	= array(
			'PropertyID'=> 5555,
			'Property Name' => 'Web hook test'
		);

		$options = array(
			'http' => array(
				'method'  	=> 'POST',
				'content' 	=> json_encode( $body ),
				'header'	=>  "Content-Type: application/json\r\n" .
				"Accept: application/json\r\n"
			)
		);

		$context  = stream_context_create( $options );
		$result = file_get_contents( $url, false, $context );
		$response = json_decode( $result );
		var_dump( $response);
		die;

	}

	public function actionPropertyCreateEndPoint(){
		$postData	= file_get_contents("php://input");
		$request	= json_decode($postData);

		global $wpdb;
		$postId = $wpdb->get_var( "SELECT post_id from $wpdb->postmeta WHERE meta_key='property_quickbase_id' AND  meta_value = $request->property_rid " );
		if($postId){
			$message 	= array(
				'status'	=> 'error',
				'message'	=> 'Duplicated property'
			);

			header('Content-Type: application/json');
			echo json_encode($message);
			die;
		}

		$my_post = array(
			'post_title' 	=> $request->property_name,
			'post_content'	=> $request->property_text,
			'post_status'	=> 'publish',
			'post_type'		=> 'property',
			'post_author'	=> 1
		);

		// Insert the post into the database
		$post_id = wp_insert_post($my_post);
		// custom fields
		update_field("property_quickbase_id", $request->property_rid, $post_id);

		$message 	= array(
			'status'	=> 'success',
			'message'	=> 'Property created successfully'
		);

		header('Content-Type: application/json');
		echo json_encode($message);
		die;

		//return $this->render('index');
	}

	public function actionPropertyUpdateEndPoint(){
		$postData	= file_get_contents("php://input");
		$request	= json_decode($postData);

		global $wpdb;
		$postId = $wpdb->get_var( "SELECT post_id from $wpdb->postmeta WHERE meta_key='property_quickbase_id' AND  meta_value = $request->property_rid " );

		$postToUpdate = array(
			'ID'	=> $postId
		);
		if(!empty($request->property_name)){
			$postToUpdate['post_title']	= $request->property_name;
		}

		if(!empty($request->property_text)){
			$postToUpdate['post_content']	= $request->property_text;
		}
		// Up	date the post into the database
		$post_id = wp_update_post( $postToUpdate );

		if (is_wp_error($post_id)) {
			$message 	= array(
				'status'	=> 'error',
				'message'	=> ''
			);
			$errors = $post_id->get_error_messages();
			foreach ($errors as $error) {
				$message['message'] .= '-  '.$error;
			}
		}else{
			$message 	= array(
				'status'	=> 'success',
				'message'	=> 'Property updated successfully'
			);
		}

		header('Content-Type: application/json');
		echo json_encode($message);
		die;
	}

	public function actionNeighborhoodCreateEndPoint(){
		$postData	= file_get_contents("php://input");
		$request	= json_decode($postData);

		global $wpdb;
		$termId = $wpdb->get_var( "SELECT term_id from wp_termmeta WHERE meta_key='neighborhood_quickbase_id' AND  meta_value = $request->neighborhood_rid" );

		if($termId){
			$message 	= array(
				'status'	=> 'error',
				'message'	=> 'Duplicated property'
			);

			header('Content-Type: application/json');
			echo json_encode($message);
			die;
		}


		$termArray 	= wp_insert_term(
			$request->neighborhood_name, // the term
			'neighborhood', // the taxonomy
			array(
				'description'=> $request->neighborhood_text,
			)
		);

		// custom fields
		update_field("neighborhood_quickbase_id", $request->neighborhood_rid, 'neighborhood_'.$termArray['term_id']);

		$message 	= array(
			'status'	=> 'success',
			'message'	=> 'Neighborhood created successfully'
		);

		header('Content-Type: application/json');
		echo json_encode($message);
		die;

		//return $this->render('index');
	}

	public function actionNeighborhoodUpdateEndPoint(){
		$postData	= file_get_contents("php://input");
		$request	= json_decode($postData);

		global $wpdb;
		$termId = $wpdb->get_var( "SELECT term_id from wp_termmeta WHERE meta_key='neighborhood_quickbase_id' AND  meta_value = $request->neighborhood_rid" );

		$termToUpdate = array();
		if(!empty($request->neighborhood_name)){
			$termToUpdate['name']	= $request->neighborhood_name;
		}

		if(!empty($request->neighborhood_text)){
			$termToUpdate['description']	= $request->neighborhood_text;
		}

		// Up	date the post into the database
		$termArray = wp_update_term($termId, 'neighborhood', $termToUpdate);

		if (is_wp_error($termArray)) {
			$message 	= array(
				'status'	=> 'error',
				'message'	=> ''
			);
			$errors = $termArray->get_error_messages();
			foreach ($errors as $error) {
				$message['message'] .= '-  '.$error;
			}
		}else{
			$message 	= array(
				'status'	=> 'success',
				'message'	=> 'Neighborhood updated successfully'
			);
		}

		header('Content-Type: application/json');
		echo json_encode($message);
		die;
	}

}