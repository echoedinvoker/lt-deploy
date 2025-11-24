<?php
namespace App\Controllers;

use App\Models\PointModel;
use App\Models\UserModel;
use App\Models\UserPsychologicalModel;
use App\Models\UserNotificationsModel;
use App\Libraries\JwtLibrary;
use App\Libraries\RedisLibrary;
use PhpOffice\PhpSpreadsheet\IOFactory;

class User extends BaseController {
	public function login()
    {
        // 獲取 JSON 請求數據
        $json = $this->request->getJSON(true); // true 表示返回關聯數組

        if(!isset($json['account']) || $json['account']==''){
            $data = [
            'status'  => false,
            'data'  => '',
            'message' => '帳號為空,請重新登入!'
            ];
            return $this->response->setJSON($data);
        }

        if(!isset($json['password']) || $json['password']==''){
            $data = [
            'status'  => false,
            'data'  => '',
            'message' => '密碼為空,請重新登入!'
            ];
            return $this->response->setJSON($data);
        }
        
    	$userModel = new UserModel();
        $where = [
            'email' => $json['account'],
        ];
        $user = $userModel->where($where)->find();

        if(!password_verify($json['password'], $user[0]['password'])){
            $data = [
            'status'  => false,
            'data'  => '',
            'message' => '密碼錯誤,請重新登入!'
            ];
            return $this->response->setJSON($data);
        }

        if(!$user[0]['is_verified']){
            $data = [
            'status'  => false,
            'data'  => '',
            'message' => '帳號未驗證,請先驗證信箱!'
            ];
            return $this->response->setJSON($data);
        }
            

        $jwt = new JwtLibrary();
        $tokenData = [
            'id' => $user[0]['id'],
            'email' => $user[0]['email'],
            'name' => $user[0]['name'],
        ];
        $token = $jwt->generateToken($tokenData);

        $data = [
        'status'  => true,
        'data'  => ['token' => $token,'uid' => $user[0]['id'],'name' => $user[0]['name']],
        'message' => 'success'
        ];

        // $redis = new RedisLibrary();
        // $redis->set('userToken:'.$user[0]['id'], $token,3600*24);

        return $this->response->setJSON($data);
    }

    public function getToken()
    {
        // $data = [
        //     'vendorClientId' => $_POST['vendorClientId'],
        //     'userToken' => $_POST['userToken'],
        // ];

        $data = $this->getUserInfo($_POST['userToken']);
        $userData = json_decode($data, true);

        $userModel = new UserModel();
        $uid = $userModel->getUid($userData['data']['email']);

        $jwt = new JwtLibrary();
        $tokenData = [
            'id' => $uid,
            'email' => $userData['data']['email'],
            'name' => $userData['data']['name'],
        ];
        $token = $jwt->generateToken($tokenData);

        $url = "https://25bta.ltrust.tw/?uid=".$uid."&token=$token";

        header("Refresh: 3; url=$url");
        exit;
    }

    public function getSchoolList()
    {
        $userModel = new UserModel();
        $list = $userModel->getSchoolList();
        $data = [
        'status'  => true,
        'data'  => $list,
        'message' => 'success'
        ];
        return $this->response->setJSON($data);
    }

    public function getUserInfo(string $userToken)
    {
        $apiUrl = 'https://vendor.ltrust.tw/api/vendor/user/info';  
        $clientId = '4a4da231-c514-47d2-93f6-7be70c770a84';  
        $key = '65f8591f2edb818cb67b3b31713d6e16';            
        $token = $userToken;            

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $apiUrl);             
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "x-client-id: $clientId",
            "x-apikey: $key",
            "x-user-token: $token",
        ]);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            // echo 'cURL 錯誤: ' . curl_error($ch);

            return curl_error($ch);
        } else {
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            // echo "HTTP 狀態碼: $httpCode\n";
            // echo "回應內容:\n$response";

            return $response;
        }

        curl_close($ch);
    }

    public function sendMessage()
    {
        $data = explode(",", $_REQUEST['ids']);
        $notifications['title']='紅利補償';
            $notifications['content']='親愛的會員 您好😊

            平台於10/25~10/28期間超商繳費異常，造成您的點數延遲發放，我們感到非常抱歉😫
            
            目前已修復完成且完成點數發放，平台特別提供您購買點數 30% 的紅利作為補償，感謝您的耐心與支持 ! 

        ';
        
        $notifications['name']='bonus_compensation';
        $usernotificationsModel = new UserNotificationsModel();

        foreach($data as $k => $v){
            $notifications['user_id']=$v;
            $usernotificationsModel->add($notifications);
        }
        return 'success';
    }

    public function readExcel()
    {
        $file = $this->request->getFile('excel');

        if (!$file->isValid()) {
            return $this->response->setJSON(['success' => false, 'message' => '檔案無效']);
        }

        // 讀取 Excel 檔案
        $spreadsheet = IOFactory::load($file->getTempName());
        $sheet = $spreadsheet->getActiveSheet();
        $data = $sheet->toArray(); // 轉成陣列格式

        $userModel = new UserModel();
        $userPsychologicalModel = new UserPsychologicalModel();
        $usernotificationsModel = new UserNotificationsModel();
        foreach($data as $k => $v){    
            if ($k === 0) continue;

            // 確保 Email 存在
            if (!isset($v[2]) || !filter_var($v[2], FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            $res = $userPsychologicalModel->checkEmailExist($v[2]);
            if($res!=null){
                $info = $userModel->getUserInfoByEmail($res);
            if($info != 0){
                $userPsychologicalModel->add($info['id'],$v[2],1);
                $pointsRes = $userModel->updateBonus($info['id'],3000,$info['bonus_points']);
                if($pointsRes == 'success'){
                    $notifications['title']='心理測驗活動獎勵';
                    $notifications['content']='親愛的同學 ，您好：

                    感謝您參加本次 LTrust 所推出的「你是哪種學習型人格」心理測驗活動！

                    您已完成 email 登記，我們已為您發送 3000 點紅利至帳戶中。

                    紅利可用於兌換 LTrust 上的各項學習服務，目前 S.E.N.S.E.I 解題教練問到飽 正在進行中，同學不要害羞，免費期間盡量用起來！

                    此外，平台也同步舉辦「紅利提款機挑戰賽」，可以再LTrust首頁BANNER上找到「Lucky7 紅利提款機大賽」的活動喔！天天完成任務還能額外賺紅利，快來看看吧💰

                    ';
                    $notifications['user_id']=$info['id'];
                    $usernotificationsModel->add($notifications);
                    }              
                } 
                else{
                    $userPsychologicalModel->add(0,$v[2],0);
                }
            }  
        }
        return $this->response->setJSON(['success' => true]);
    }

            public function readExcelRegister()
    {
        $file = $this->request->getFile('excel');

        if (!$file->isValid()) {
            return $this->response->setJSON(['success' => false, 'message' => '檔案無效']);
        }

        // 讀取 Excel 檔案
        $spreadsheet = IOFactory::load($file->getTempName());
        $sheet = $spreadsheet->getActiveSheet();
        $data = $sheet->toArray(); // 轉成陣列格式

        $userModel = new UserModel();
        $userPsychologicalModel = new UserPsychologicalModel();
        $usernotificationsModel = new UserNotificationsModel();
        foreach($data as $k => $v){
            if ($k === 0) continue;

            // 確保 Email 存在
            if (!isset($v[2]) || !filter_var($v[2], FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            $res = $userPsychologicalModel->checkEmailExist($v[2]);
            if($res!=null){
                $info = $userModel->getUserInfoByEmail($res);
            if($info != 0){
                $userPsychologicalModel->add($info['id'],$v[2],1);
                $pointsRes = $userModel->updateBonus($info['id'],100,$info['bonus_points']);
                if($pointsRes == 'success'){
                    $notifications['title']='叫我註冊王_2_email活動獎勵';
                    $notifications['content']='親愛的同學 ，您好：

                    叮咚～龍騰高中聲 LINE 推播好禮來囉！🎉

                    恭喜同學獲得 100 紅利！

                    這 100 紅利可用於購買「叫我註冊王」活動推薦碼，邀請同學一起註冊 LTrust！邀請越多朋友註冊完成，就有機會獲得最高 新台幣 3,000 元獎金。天大好機會不要錯過啦！

                    想知道更多「叫我註冊王」活動資訊 👉 https://cmrk.ltrust.tw/

                    ';
                    $notifications['user_id']=$info['id'];
                    $usernotificationsModel->add($notifications);
                    }              
                } 
                else{
                    $userPsychologicalModel->add(0,$v[2],0);
                }
            }
        }
        return $this->response->setJSON(['success' => true]);
    }

            public function readExcelSend()
    {
        $file = $this->request->getFile('excel');

        if (!$file->isValid()) {
            return $this->response->setJSON(['success' => false, 'message' => '檔案無效']);
        }

        // 讀取 Excel 檔案
        $spreadsheet = IOFactory::load($file->getTempName());
        $sheet = $spreadsheet->getActiveSheet();
        $data = $sheet->toArray(); // 轉成陣列格式

        $userModel = new UserModel();
        $userPsychologicalModel = new UserPsychologicalModel();
        $usernotificationsModel = new UserNotificationsModel();
        foreach($data as $k => $v){var_dump($v[1]);
            if ($k === 0) continue;

            // 確保 Email 存在
            if (!isset($v[1]) || !filter_var($v[1], FILTER_VALIDATE_EMAIL)) {
                continue;var_dump($v[1]);var_dump("!!!!!!");
            }

            $res = $userPsychologicalModel->checkEmailExist($v[1]);
            if($res!=null){
                $info = $userModel->getUserInfoByEmail($res);
            if($info != 0){
                $userPsychologicalModel->add($info['id'],$v[1],1,'學測通行證通知');
                $notifications['title']='【限時 33 折】國英數 210 題精選＋全科 Qbot 問到飽｜高三學測通行證開賣！';
                    $notifications['content']='同學您好：

                        學測進入倒數，最完整、最划算的備考組合 「高三專屬｜學測通行證」 已正式推出！

                        我們把你在最後衝刺階段最需要的工具全部一次打包：

                        ✔S.E.N.S.E.I 國英數精選題組（每科 70 題，共 210 題）

                        拍題就能立即看到 清楚解析＋題型提醒，協助你補強基礎、掌握常錯題。

                        ✔Qbot 全科刷題問到飽

                        不限科目、不限冊次，想練就練，隨時保持手感不生鏽。

                        原價加起來共 6,067 元，現在 限時 33 折，只要 1,980 元 就能一次擁有。
                        ________________________________________
                        Q：通行證在哪裡購買？

                        A：登入後回首頁，右上角購物車旁的 【通】ICON 就能找到購買入口！
                        ________________________________________
                        有 AI 幫你拆題，有題庫陪你練熟，讓你在剩下的時間更有效率、更有方向。

                        祝你備考順利，離目標大學再近一步。

                        — LTrust 團隊

                    ';
                    $notifications['user_id']=$info['id'];
                    $usernotificationsModel->add($notifications);
                    }  
            }
        }
        return $this->response->setJSON(['success' => true]);
    }
    
    public function supplyLog()
    {
        $userPsychologicalModel = new UserPsychologicalModel();
        $res = $userPsychologicalModel->getLog('2025-10-01');

        $userModel = new UserModel();
        $pointModel = new PointModel();
        foreach($res as $k => $v){
            $info = $userModel->getUserInfo($v['uid']);
            $before = $info['bonus_points']-100;
            $pointModel->addRegisterBonusLog($v['uid'],100,$before);
        }
        return 'success';
    }
}
