<?php
require '../../../zb_system/function/c_system_base.php';
$zbp->Load();
if (!$zbp->CheckRights('root')) {$zbp->ShowError(6);exit();}
if (!$zbp->CheckPlugin('changyan')) {$zbp->ShowError(48);exit();}
/*
    add_action('wp_ajax_changyan_sync2WordPress', array($changyanPlugin, 'sync2Wordpress'));
    add_action('wp_ajax_changyan_sync2Changyan', array($changyanPlugin, 'sync2Changyan'));
    add_action('wp_ajax_changyan_saveScript', array($changyanPlugin, 'saveScript'));
    add_action('wp_ajax_changyan_saveAppID', array($changyanPlugin, 'saveAppID'));
    add_action('wp_ajax_changyan_saveAppKey', array($changyanPlugin, 'saveAppKey'));
    add_action('wp_ajax_changyan_cron', array($changyanPlugin, 'setCron'));
*/
if(isset($_POST['action'])){

	if($_POST['action']=='changyan_sync2Wordpress'){
		$changyanPlugin->sync2Wordpress();
	}

	if($_POST['action']=='changyan_sync2Changyan'){
		$changyanPlugin->sync2Changyan();
	}

	if($_POST['action']=='changyan_saveScript'){
		$changyanPlugin->saveScript();
	}

	if($_POST['action']=='changyan_saveAppID'){
		$changyanPlugin->saveAppID();
	}

	if($_POST['action']=='changyan_saveAppKey'){
		$changyanPlugin->saveAppKey();
	}
	
	if($_POST['action']=='changyan_setCron'){
		$changyanPlugin->setCron();
	}

}