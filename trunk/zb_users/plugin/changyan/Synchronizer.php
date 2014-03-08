<?php
ini_set('max_execution_time', '0');
class Changyan_Synchronizer
{
    private static $instance = null;
    private $PluginURL = 'changyan';

    private function __construct()
    {
        $this->PluginURL = plugin_dir_url(__FILE__);
    }

    private function __clone()
    {
        //Prevent from being cloned
    }

    //return the single instance of this class
    public static function getInstance()
    {
        if (!(self::$instance instanceof self)) {
            self::$instance = new self;
        }
        return self::$instance;
    }

    public function sync2Wordpress(){
        global $zbp;
	
        @set_time_limit(0);
        @ini_set('memory_limit', '256M');

        $script = $this->getOption('changyan_script');
        $appID = explode("'", $script);
        //Now we get the appID from the script
        $appID = $appID[1];

        //not site_url: the folder of wp installed, see http://www.andelse.com/wordpress-url-strategy-url-function-list.html
        //echo "<br/>".home_url();//it is right
        //get post list from
        $nextID2CY = $this->getOption('changyan_sync2CY');
        $nextID2WP = $this->getOption('changyan_sync2WP');

        if (empty($nextID2CY)) {
			$cmts = $zbp->GetCommentList('*', null, array('comm_PostTime' => 'ASC'), 1, null);
			if(count($cmts)>0){
				$nextID2CY=$cmts[0]->ID;
			}else{
				$nextID2CY = 1;
			}
        }
        if (empty($nextID2WP)) {
			$cmts = $zbp->GetCommentList('*', null, array('comm_PostTime' => 'ASC'), 1, null);
			if(count($cmts)>0){
				$nextID2CY=$cmts[0]->ID;
			}else{
				$nextID2WP = 1;
			}
        }
        //make sure $nextID2WP is the largest
        if ($nextID2CY > $nextID2WP) {
            $nextID2WP = $nextID2CY;
        }
		
		$cmt=$zbp->GetCommentByID($nextID2WP);
        if ($cmt->ID==0) {
            die("同步成功，没有需要同步的评论");
        }else{
			$time = date ( "Y-m-d H:i:s",$cmt->PostTime );
		}
		
        $params = array(
            'appId' => $appID,
            'date' => $time
        );

        $URL = "http://changyan.sohu.com/admin/api/recent-comment-topics";
        $URL = $this->buildURL($params, $URL);
        //echo current_time( 'mysql' ).'<br/>';//xcv
        //echo "URL:<br/>"; print_r($URL); echo "<br/>";//xcv
        $data = $this->getContents_curl($URL);
        $data = json_decode($data);
        //echo current_time( 'mysql' ).'<br/>';//xcv
        //echo "data<br/>"; print_r($data); echo "<br/>";//xcv
        if (empty($data->success)) {
            die("同步失败:服务器返回空值");
        }
        if ('false' === $data->success) {
            die("同步失败:" . ($data->success));
        }
		
		$postGroup = $data->topics;
		
		var_dump($data->topics);
	}
	
    #region 'Synchronize to WordPress'
    public function sync2Wordpress1()
    {
        global $wpdb;
        @set_time_limit(0);
        @ini_set('memory_limit', '256M');

        $script = $this->getOption('changyan_script');
        $appID = explode("'", $script);
        //Now we get the appID from the script
        $appID = $appID[1];

        //not site_url: the folder of wp installed, see http://www.andelse.com/wordpress-url-strategy-url-function-list.html
        //echo "<br/>".home_url();//it is right
        //get post list from
        $nextID2CY = $this->getOption('changyan_sync2CY');
        $nextID2WP = $this->getOption('changyan_sync2WP');

        if (empty($nextID2CY)) {
            $nextID2CY = 1;
        }
        if (empty($nextID2WP)) {
            $nextID2WP = 1;
        }
        //make sure $nextID2WP is the largest
        if ($nextID2CY > $nextID2WP) {
            $nextID2WP = $nextID2CY;
        }

        $time = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT comment_date AS time FROM $wpdb->comments WHERE comment_ID = %s",
                $nextID2WP
            )
        );
        //echo current_time( 'mysql' ).'<br/>';//xcv
        //echo "nextid2wp: ".$nextID2WP."<br/>";//xcv
        //echo "Time is ";print_r($time);echo "<br/>";//xcv
        if (empty($time)) {
            die("同步成功，没有需要同步的评论");
        }
        $time = $time[0]->time;

        $params = array(
            'appId' => $appID,
            'date' => $time
        );

        $URL = "http://changyan.sohu.com/admin/api/recent-comment-topics";
        $URL = $this->buildURL($params, $URL);
        //echo current_time( 'mysql' ).'<br/>';//xcv
        //echo "URL:<br/>"; print_r($URL); echo "<br/>";//xcv
        $data = $this->getContents_curl($URL);
        $data = json_decode($data);
        //echo current_time( 'mysql' ).'<br/>';//xcv
        //echo "data<br/>"; print_r($data); echo "<br/>";//xcv
        if (empty($data->success)) {
            die("同步失败:服务器返回空值");
        }
        if ('false' === $data->success) {
            die("同步失败:" . ($data->success));
        }

        $postGroup = $data->topics;
        //echo current_time( 'mysql' ).'<br/>';//xcv
        //echo "服务器返回的topic列表<br/>";print_r($postGroup);echo "<br/>";//xcv

        $postAll = $wpdb->get_results(
            "SELECT ID AS ID, post_title AS title
                FROM $wpdb->posts
                WHERE post_type NOT IN ('attachment', 'nav_menu_item', 'revision')
                AND post_status NOT IN ('future', 'auto-draft', 'draft', 'trash', 'inherit')
                ORDER BY ID DESC"
        );
        //echo "PostAll: <br/>";print_r($postAll);echo "<br/>";//xcv
        $postArray = array();
        foreach ($postGroup as $aPG) {
            foreach ($postAll as $aPost) {
                $aPostUrl = get_permalink($aPost->ID);
                if (strcasecmp($aPostUrl, $aPG->topic_url) == 0) {
                    $postArray[] = array(
                        'ID' => $aPost->ID,
                        'post_title' => $aPost->title
                    );
                }
            }
        }

        $lastCommentID = $this->getOption('changyan_sync2WP');
        if (empty($lastCommentID)) {
            $lastCommentID = 0;
        }
        //echo current_time( 'mysql' ).'<br/>';//xcv
        //echo "postArray: <br/>";print_r($postArray);echo "<br/>";//xcv
        foreach ($postArray as $aPost) {
            //cyanCommentList is array of comment pages
            //echo '> '.current_time( 'mysql' ).'<br/>';//xcv
            $cyanCommentList = $this->getCommentList_curl($appID, $aPost);
            //echo "cyanCMTList:<br/>";//xcv
            //print_r($cyanCommentList);//xcv
            //get comment informations object generated by Changyan server
            //echo '>> '.current_time( 'mysql' ).'<br/>';//xcv
            $commentID = $this->insertComments($cyanCommentList, $aPost['ID']);
            //echo '>>> '.current_time( 'mysql' ).'<br/>';//xcv
            if ($commentID > $lastCommentID) {
                $lastCommentID = $commentID;
            }
        }
        //echo '*** '.current_time( 'mysql' ).'<br/>';//xcv
        //recode the latest synchronization time
        $this->setOption('changyan_lastSyncTime', date("Y-m-d G:i:s", time() + get_option('gmt_offset') * 3600));
        $this->setOption('changyan_sync2WP', $lastCommentID);
        //echo $lastCommentID;//xcv
        die("同步成功");
    }

    //get comment list through cURL
    //return Array
    private function getCommentList_curl($appID, $aPost)
    {
        #region 'Using api/open/topic/load to get topic_id in Changyan'
        //generate the params
        $data = array(
            'client_id' => $appID,
            'topic_url' => get_permalink($aPost['ID']),
            'topic_title' => $aPost['post_title'],
            'style' => 'terrace'
        );
        $data = http_build_query($data);

        //append the params behind the url
        $sUrl = 'https://changyan.sohu.com/api/open/topic/load';
        $sUrl .= ("?" . $data);

        //execute GET through cURL
        $data = $this->getContents_curl($sUrl);
        $data = json_decode($data);
        // echo "xx<br/>";print_r($data);//xcv
        if (!empty($data->error_code)) {
            die("同步失败：" . ($data->error_code));
        }
        $topic_id = $data->topic_id;
        #endregion

        #region 'Using api/open/comment/list to get comment list in Changyan'
        //page_no is the current comment page number
        $page_no = 1;
        //page_sum is the sum of comment pages
        $page_sum = 1;
        //commentPageArray is array of the comment pages
        $commentPageArray = array();

        while ($page_no <= $page_sum) {
            //clear $data
            unset($data);
            //generate the params
            $data = array(
                'client_id' => $appID,
                'topic_id' => $topic_id,
                'outer_page_no' => $page_no,
                'style' => 'terrace'
            );

            //append the params behind the url
            $sUrl = 'https://changyan.sohu.com/api/open/comment/list';
            $sUrl = $this->buildURL($data, $sUrl);

            //execute GET through cURL
            $data = $this->getContents_curl($sUrl);
            //$data is object now
            $data = json_decode($data);

            $page_sum = intval(($data->cmt_sum) / 30) + 1;

            //insert data into $commentPageArray
            $commentPageArray[] = $data;
            $page_no += 1;
        }
        #endregion
        return $commentPageArray;
    }

    //build GET URL by data array and base url
    public function buildURL($dataArray, $baseURL)
    {
        $dataSection = http_build_query($dataArray);
        $URL = $baseURL;
        $URL .= ("?" . $dataSection);

        return $URL;
    }

    /**
     * execute GET function using cURL, return JSON
     */
    public function getContents_curl($aUrl)
    {
        //check if exits
        if (!function_exists('curl_init')) {
            throw new Exception('server not install curl');
        }
        //curl intialization and execution
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $aUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        if (ini_get('open_basedir') == '' && ini_get('safe_mode' == 'Off')) {
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        }
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 900);

        $data = curl_exec($ch);

        $error = curl_error($ch);
        //close
        if ($data == false || !empty($error)) {
            curl_close($ch);
            die("同步失败: " . $error);
        }
        @curl_close($ch);

        return $data;
    }

    /**
     * get comment information object generated by changyan server
     *
     * @param $cmts object of a node of the comment page array file
     * @param $postID id of the post which the comments reply to
     * @return int Array of comments array
     */
    private function insertComments($cmts, $postID)
    {
        //the list of the raw comments retrieved from $cmts
        $commentsArray = array();
        //the list of the comments of WPDB format to be inserted
        //$commentsList = array();
        //the map of the comment, map(key = $comment.'_'.$time, value = $comment_id); Assuming that no wordpress site will synchronize more than 256MB comments
        $commentsMap = array();
        global $wpdb;

        //transform comments in object array $cmts to $commentsArray one by one
        foreach ($cmts as $cmt) {
            foreach (($cmt->comments) as $aComment) {
                $commentsArray[] = $aComment;
            }
        }

        //make sure the comments in this array is in ascend order by create_time
        $commentsArray = array_reverse($commentsArray);
        usort($commentsArray, array($this, 'cmtAscend'));
        //limit of the commentmMap is 100000 comments
        $countMap = 0;
        $commentID = 0;
        //print_r($commentsArray[5]);//xcv
        //print_r($commentsArray[555]);//xcv
        for ($i = 0; $i < count($commentsArray); $i++) {
            $replyto = "";
            $commentParent = "";
            if (is_array($commentsArray[$i]->comments) && !empty($commentsArray[$i]->comments)) {
                //ensure the parent comment index is 0
                usort($commentsArray[$i]->comments, array($this, 'cmtDescend'));
                //replyto uses gmt time
                if(!empty($commentsArray[$i]->comments[0])) {
                    $replyto = ($commentsArray[$i]->comments[0]->passport->nickname) . "_" . ($commentsArray[$i]->comments[0]->content) . "_" . (date("Y-m-d G:i:s", ($commentsArray[$i]->comments[0]->create_time) / 1000));
                }
            }
            //if this comment has a parent comment
            if (!empty($replyto)) {
                //echo "replyto ".$replyto."<br/>";//xcv
                if (array_key_exists($replyto, $commentsMap)) {
                    $commentParent = $commentsMap[$replyto];
                } else { // the parent comment not in this map, e.g. in the wpdb
                    $str = explode("_", $replyto);
                    $commentParent = $wpdb->get_results(
                        $wpdb->prepare(
                            "SELECT comment_ID FROM $wpdb->comments
                                WHERE comment_post_ID = %s
                                AND comment_date_gmt = %s
                                AND comment_content = %s
                                AND comment_author = %s",
                            $postID,
                            $str[2],
                            $str[1],
                            $str[0]
                        )
                    );
                    if (is_array($commentParent) && !empty($commentParent)) {
                        $commentParent = $commentParent[0]->comment_ID;
                    } else {
                        $commentParent = "";
                    }
                }
            }

            $comment = array(
                'comment_post_ID' => $postID, //0
                'comment_author' => $commentsArray[$i]->passport->nickname, //1
                'comment_author_email' => '', //2
                'comment_author_url' => $commentsArray[$i]->passport->profile_url, //3
                'comment_author_IP' => $commentsArray[$i]->ip_location, //4
                'comment_date' => date("Y-m-d G:i:s", (($commentsArray[$i]->create_time) / 1000 + get_option('gmt_offset') * 3600)), //5
                'comment_date_gmt' => date("Y-m-d G:i:s", ($commentsArray[$i]->create_time) / 1000), //6
                'comment_content' => addslashes($commentsArray[$i]->content), //7
                'comment_karma' => "0", //8
                'comment_approved' => "1", //9
                'comment_agent' => "Changyan_" . $commentsArray[$i]->comment_id, //10
                'comment_type' => "", //11
                'comment_parent' => $commentParent, //12
                'user_id' => "", //13
            );

            if (true === $this->isCommentExits($postID, $comment['comment_author'], $comment['comment_content'], $comment['comment_date_gmt'])) {
                continue;
            }

            $commentID = wp_insert_comment($comment);
            if ($countMap < 100000) {
                $commentsMap[$comment['comment_author'] . "_" . $comment['comment_content'] . "_" . $comment['comment_date_gmt']] = $commentID;
                $countMap += 1;
            }
        }

        return $commentID;
    }

    //This is a comparation function used by usort in function insertComments()
    private function cmtAscend($x, $y)
    {
        return (intval($x->create_time)) > (intval($y->create_time)) ? 1 : -1;
    }

    //This is a comparation function used by usort in function insertComments()
    private function cmtDescend($x, $y)
    {
        return (intval($x->create_time)) > (intval($y->create_time)) ? -1 : 1;
    }

    //to check if this comment is in the wpdb
    private function isCommentExits($post_id, $comment_author, $comment_content, $date_gmt)
    {
        global $wpdb;
        $comment = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT comment_ID FROM $wpdb->comments
                    WHERE  comment_post_ID = %s
                    AND comment_content = %s
                    AND comment_date_gmt = %s
                    AND comment_author = %s",
                $post_id,
                stripslashes($comment_content),
                $date_gmt,
                $comment_author
            )
        );

        if (is_array($comment) && !empty($comment)) {
            return true;
        } else {
            return false;
        }
    }

    #endregion

    #region 'Synchronize to Changyan'

    public function sync2Changyan($isSetup = false)
    {
        global $wpdb;
        @set_time_limit(0);
        @ini_set('memory_limit', '256M');

        $nextID2CY = $this->getOption('changyan_sync2CY');

        if (empty($nextID2CY)) {
            $nextID2CY = 1;
        }

        $maxID = $wpdb->get_results(
            "SELECT MAX(comment_ID) AS maxID FROM $wpdb->comments
                WHERE comment_agent NOT LIKE '%%Changyan%%'"
        );
        $maxID = $maxID[0]->maxID;

        $postIDList = $wpdb->get_results($wpdb->prepare(
            "SELECT DISTINCT comment_post_ID FROM $wpdb->comments
                WHERE comment_ID > %s
                AND comment_ID <= %s",
            $nextID2CY,
            $maxID
        ));
        //echo "nextID2CY is ".$nextID2CY.";maxID is ".$maxID."<br/>";//xcv
        //echo "postIDlist: <br/>";print_r($postIDList);echo "<br/>";//xcv
        $maxPostID = $wpdb->get_results("SELECT MAX(ID) AS maxPostID FROM $wpdb->posts"); //
        $maxPostID = $maxPostID[0]->maxPostID; //
        //flag of response
        $flag = true;
        $response = "";

        foreach ($postIDList as $aPost) {
            //in case of bug of duoshuo or other plugins: postID larger than maxPostID
            //echo ($aPost -> comment_post_ID)."  ";//////////////////xcv
            if ($aPost->comment_post_ID > $maxPostID) {
                continue;
            }
            $postInfo = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT ID AS post_ID,
                        post_title AS post_title,
                        post_date AS post_time,
                        post_parent AS post_parents
                        FROM $wpdb->posts
                        WHERE post_type NOT IN ('attachment', 'nav_menu_item', 'revision')
                        AND post_status NOT IN ('future', 'auto-draft', 'draft', 'trash', 'inherit')
                        AND ID = %s",
                    $aPost->comment_post_ID
                )
            );
            //build the comments to be synchronized
            $topic_url = get_permalink($postInfo[0]->post_ID);
            $topic_title = $postInfo[0]->post_title;
            $topic_time = $postInfo[0]->post_time;
            $topic_id = $postInfo[0]->post_ID;
            $topic_parents = ""; //$postInfo[0]->post_parents;
            $script = $this->getOption('changyan_script');
            $appID = explode("'", $script);
            //get the appID from the script
            $appID = $appID[1];
            //echo $topic_title."<br/>";//////////////////////xcv
            if ($isSetup == true) {
                $commentsList = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT * FROM  $wpdb->comments
                            WHERE comment_post_ID = %s
                            AND comment_ID BETWEEN %s AND %s",
                        $postInfo[0]->post_ID,
                        $nextID2CY,
                        $maxID
                    )
                );
            } else {
                $commentsList = $wpdb->get_results(
                    $wpdb->prepare(
                        "SELECT * FROM $wpdb->comments
                            WHERE comment_post_ID = %s
                            AND comment_agent NOT LIKE '%%Changyan%%'
                            AND comment_ID BETWEEN %s AND %s",
                        $postInfo[0]->post_ID,
                        $nextID2CY,
                        $maxID
                    )
                );
                //echo "postID: ".$postInfo[0]->post_ID." :<br/>";print_r($commentsList);echo "<br/>";//xcv
            }
            $comments = array();
            //insert comments into the commentsArray
            foreach ($commentsList as $comment) {
                $user = array(
                    'userid' => $comment->user_id,
                    'nickname' => $comment->comment_author,
                    'usericon' => '',
                    'userurl' => $comment->comment_author_url
                );
                $comments[] = array(
                    'cmtid' => $comment->comment_ID,
                    'ctime' => $comment->comment_date,
                    'content' => $comment->comment_content,
                    'replyid' => $comment->comment_parent,
                    'user' => $user,
                    'ip' => $comment->comment_author_IP,
                    'useragent' => $comment->comment_agent,
                    'channeltype' => '1',
                    'from' => '',
                    'spcount' => '',
                    'opcount' => ''
                );
            }
            //comments under a post to be synchronized
            $postComments = array(
                'title' => $topic_title,
                'url' => $topic_url,
                'ttime' => $topic_time,
                'sourceid' => "",
                'parentid' => $topic_parents,
                'categoryid' => '',
                'ownerid' => '',
                'metadata' => '',
                'comments' => $comments
            );

            if (empty($comments)) {
                continue;
            }

            //get the appID from the script
            $script = $this->getOption('changyan_script');
            $appID = explode("'", $script);
            $appID = $appID[1];

            $postComments = json_encode($postComments);

            //hmac encode
            $appKey = $this->getOption('changyan_appKey');
            $appKey = trim($appKey);
            $md5 = hash_hmac('sha1', $postComments, $appKey);

            $postData = "appid=" . $appID . "&md5=" . $md5 . "&jsondata=" . $postComments;
            //print_r($postData);////////////xcv//////////
            $response = $this->postContents_curl("http://changyan.sohu.com/admin/api/import/comment", $postData);
            $regex = '/success":true/';
            //if "true" not found
            //echo "Response is ".$response;//xcv
            if (!preg_match($regex, $response)) {
                $flag = false;
                break;
            }
        }
        if ($flag === true) {
            //recode the latest synchronization time
            $this->setOption('changyan_lastSyncTime', date("Y-m-d G:i:s", time() + get_option('gmt_offset') * 3600));
            $this->setOption('changyan_sync2CY', $maxID);
            die("同步成功");
        } else {
            die("同步失败:" . $response);
        }
    }

    //execute POST function using cURL and return JSON array containing comment_ids in Changyan
    private function postContents_curl($aUrl, $aCommentsArray, $headerPart = array())
    {
        //check if exits
        if (!function_exists('curl_init')) {
            throw new Exception('server not install curl');
        }

        //curl intialization and execution
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $aUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 900);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $aCommentsArray);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        //to do a HTTP POST of over 1024 characters
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json', 'Expect:'));

        $data = curl_exec($ch);

        $error = curl_error($ch);
        //close
        if ($data == false || !empty($error)) {
            curl_close($ch);
            die("Error: " . $error);
        }
        @curl_close($ch);

        return $data;
    }

    #endregion

    private function getOption($option)
    {
		global $zbp;
		return $zbp->Config('changyan')->$option;
        //return get_option($option);
    }

    private function setOption($option, $value)
    {
		global $zbp;
		$zbp->Config('changyan')->$option=$value;
		$zbp->SaveConfig('changyan');
		return true;
        //return update_option($option, $value);
    }

    private function delOption($option)
    {
		global $zbp;
		$zbp->Config('changyan')->Del($option);
		$zbp->SaveConfig('changyan');
		return true;
        //return delete_option($option);
    }

    private function showAllComments()
    {
        global $wpdb;
        $cmtlist = $wpdb->get_results("SELECT * FROM $wpdb->comments", ARRAY_A);
        foreach ($cmtlist as $aCmt) {
            foreach ($aCmt as $v) {
                echo $v . ";  ";
            }
            echo "<br/>";
        }
    }
}

?>