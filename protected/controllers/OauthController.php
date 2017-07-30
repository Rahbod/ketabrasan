<?php
class OauthController extends ApiBaseController
{
    protected $request = null;

    public function beforeAction($action)
    {
        $this->request = $this->getRequest();
        return parent::beforeAction($action); // TODO: Change the autogenerated stub
    }

    public function actionAuthorize()
    {
        if(isset($this->request['email']) and isset($this->request['password'])){
            $login = new UserLoginForm;
            $login->scenario = 'app_login';
            $login->email = $this->request['email'];
            $login->password = $this->request['password'];
            if($login->validate() && $login->login()){
                $this->_sendResponse(200, CJSON::encode([
                    'status' => true,
                    'authorization_code' => session_id()
                ]), 'application/json');
            }else
                $this->_sendResponse(400, CJSON::encode([
                    'status' => false,
                    'message' => $login->getError('authenticate_field')
                ]),
                    'application/json');
        }else
            $this->_sendResponse(400, CJSON::encode(['status' => false, 'message' => 'Email and Password is required.']), 'application/json');
    }

    public function actionToken(){
        if(isset($this->request['grant_type'])){
            if($this->request['grant_type']=='refresh_token' && isset($this->request['refresh_token'])){
                $refresh_token = $this->request['refresh_token'];
                $refresh_token = Yii::app()->JWS->decode($refresh_token);
                if(!$refresh_token)
                    $this->_sendResponse(401, CJSON::encode(['status' => false,
                        'message' => 'Refresh Token is invalid.']), 'application/json');
                $refresh_token = $refresh_token->str;
                if(!$refresh_token)
                    $this->_sendResponse(401, CJSON::encode(['status' => false,
                        'message' => 'Refresh Token is invalid.']), 'application/json');
                $session = Sessions::model()->findByAttributes(array('refresh_token' => $refresh_token));
                if($session === null)
                    $this->_sendResponse(401, CJSON::encode(['status' => false,
                        'message' => 'Refresh Token has expired. Please Authorize again.']), 'application/json');
                if(!$session->user)
                    $this->_sendResponse(401, CJSON::encode(['status' => false,
                        'message' => 'Refresh Token is invalid. Please Authorize again.']), 'application/json');
                @session_start();
                session_regenerate_id($session->id);
                $newID=session_id();
                @session_destroy();
                $session->id = $newID;
                $session->expire = time()+Yii::app()->session->timeout;
                if($session->save()){
                    $access_token = Yii::app()->JWT->encode([
                        'email' => $session->user->email,
                        'user_id' => $session->user->id,
                        'session_id' => $newID
                    ]);
                    $this->_sendResponse(200, CJSON::encode([
                        'status' => true,
                        'token' => [
                            'access_token' => $access_token,
                            'token_type' => 'Bearer',
                            'expire_in' => Yii::app()->session->timeout,
                        ]
                    ]), 'application/json');
                }else
                    $this->_sendResponse(401, CJSON::encode(['status' => false,
                        'message' => 'Generate new access token failed. Please try again.']), 'application/json');
            }else if($this->request['grant_type']=='access_token' && isset($this->request['authorization_code'])){
                $code = $this->request['authorization_code'];
                $session = Sessions::model()->findByPk($code);
                if($session === null)
                    $this->_sendResponse(400, CJSON::encode([
                        'status' => false,
                        'message' => 'Authorization code is invalid.'
                    ]), 'application/json');
                $user = $session->user;
                $access_token = Yii::app()->JWT->encode([
                    'email' => $user->email,
                    'user_id' => $user->id,
                    'session_id' => $session->id
                ]);
                $refresh_token = Yii::app()->JWS->encode([
                    'str' => $session->refresh_token
                ]);
                $this->_sendResponse(200, CJSON::encode([
                    'status' => true,
                    'token' => [
                        'access_token' => $access_token,
                        'token_type' => 'Bearer',
                        'expire_in' => Yii::app()->session->timeout,
                        'refresh_token' => $refresh_token,
                    ]
                ]), 'application/json');
            }else if($this->request['grant_type']=='revoke_token' && isset($this->request['access_token'])){
                $access_token = $this->request['access_token'];
                $token = Yii::app()->JWT->decode($access_token);
                if(!$token)
                    $this->_sendResponse(401, CJSON::encode(['status' => false,
                        'code' => 104,
                        'message' => 'Access Token is invalid.']), 'application/json');
                if(!$token->session_id)
                    $this->_sendResponse(401, CJSON::encode(['status' => false,
                        'code' => 104,
                        'message' => 'Access Token is invalid.']), 'application/json');

                $session = Sessions::model()->findByPk($token->session_id);
                if($session)
                    $session->delete();
                $this->_sendResponse(200, CJSON::encode([
                    'status' => true,
                    'message' => 'Access token has revoked successfully.'
                ]), 'application/json');
            }else
                $this->_sendResponse(400, CJSON::encode(['status' => false, 'message' => 'request invalid.']), 'application/json');
        }else
            $this->_sendResponse(400, CJSON::encode(['status' => false, 'message' => 'grant_type not sent.']), 'application/json');
    }
}