<?php

$installedVersion = OCP\Config::getAppValue('files_sharing', 'installed_version');

// clean up oc_share table from files which are no longer exists
if (version_compare($installedVersion, '0.3.5.6', '<')) {
	\OC::$server->getLogger()->warning('start fixBrokenSharesOnAppUpdate', array('app' => 'files_sharing_update'));
	\OC\Files\Cache\Shared_Updater::fixBrokenSharesOnAppUpdate();
}

if (version_compare($installedVersion, '0.4', '<')) {
	\OC::$server->getLogger()->warning('start removeSharedFolder', array('app' => 'files_sharing_update'));
	removeSharedFolder();
}

if (version_compare($installedVersion, '0.5', '<')) {
	\OC::$server->getLogger()->warning('start updateFilePermissions', array('app' => 'files_sharing_update'));
	updateFilePermissions();
}


/**
 * it is no longer possible to share single files with delete permissions. User
 * should only be able to unshare single files but never to delete them.
 */
function updateFilePermissions($chunkSize = 99) {
	$query = OCP\DB::prepare('SELECT * FROM `*PREFIX*share` WHERE `item_type` = ?');
	$result = $query->execute(array('file'));

	$updatedRows = array();

	while ($row = $result->fetchRow()) {
		if ($row['permissions'] & \OCP\PERMISSION_DELETE) {
			$updatedRows[$row['id']] = (int)$row['permissions'] & ~\OCP\PERMISSION_DELETE;
		}
	}

	$connection = \OC_DB::getConnection();
	$chunkedPermissionList = array_chunk($updatedRows, $chunkSize, true);

	foreach ($chunkedPermissionList as $subList) {
		\OC::$server->getLogger()->warning('updateFilePermissions ' . json_encode($subList), array('app' => 'files_sharing_update'));
		$statement = "UPDATE `*PREFIX*share` SET `permissions` = CASE `id` ";
		//update share table
		$ids = implode(',', array_keys($subList));
		foreach ($subList as $id => $permission) {
			$statement .= "WHEN " . $connection->quote($id, \PDO::PARAM_INT) . " THEN " . $permission . " ";
		}
		$statement .= ' END WHERE `id` IN (' . $ids . ')';

		$query = OCP\DB::prepare($statement);
		$query->execute();
	}

}

/**
 * update script for the removal of the logical "Shared" folder, we create physical "Shared" folder and
 * update the users file_target so that it doesn't make any difference for the user
 * @note parameters are just for testing, please ignore them
 */
function removeSharedFolder($mkdirs = true, $chunkSize = 99) {
	$logger = \OC::$server->getLogger();
	$query = OCP\DB::prepare('SELECT * FROM `*PREFIX*share`');
	$result = $query->execute();
	$view = new \OC\Files\View('/');
	$users = array();
	$shares = array();
	$logger->warning('removeSharedFolder init', array('app' => 'files_sharing_update'));
	//we need to set up user backends
	OC_User::useBackend(new OC_User_Database());
	OC_Group::useBackend(new OC_Group_Database());
	OC_App::loadApps(array('authentication'));
	$logger->warning('removeSharedFolder apps loaded', array('app' => 'files_sharing_update'));
	//we need to set up user backends, otherwise creating the shares will fail with "because user does not exist"
	while ($row = $result->fetchRow()) {
		//collect all user shares
		if ((int)$row['share_type'] === 0 && ($row['item_type'] === 'file' || $row['item_type'] === 'folder')) {
			$users[] = $row['share_with'];
			$shares[$row['id']] = $row['file_target'];
		} else if ((int)$row['share_type'] === 1 && ($row['item_type'] === 'file' || $row['item_type'] === 'folder')) {
			//collect all group shares
			$users = array_merge($users, \OC_group::usersInGroup($row['share_with']));
			$shares[$row['id']] = $row['file_target'];
		} else if ((int)$row['share_type'] === 2) {
			$shares[$row['id']] = $row['file_target'];
		}
	}
	$logger->warning('removeSharedFolder rows fetched', array('app' => 'files_sharing_update'));

	$unique_users = array_unique($users);

	if (!empty($unique_users) && !empty($shares)) {

		// create folder Shared for each user

		if ($mkdirs) {
			$total = count($unique_users);
			$i = 1;
			foreach ($unique_users as $user) {
				$logger->warning('removeSharedFolder setup ' . $user . ' ('. $i .'/'.$total. ')', array('app' => 'files_sharing_update'));
				$i++;
				\OC\Files\Filesystem::initMountPoints($user);
				$logger->warning('removeSharedFolder mounts finished', array('app' => 'files_sharing_update'));
				if (!$view->file_exists('/' . $user . '/files/Shared')) {
					$logger->warning('removeSharedFolder create Shared/', array('app' => 'files_sharing_update'));
					$view->mkdir('/' . $user . '/files/Shared');
				}
			}
		}

		$chunkedShareList = array_chunk($shares, $chunkSize, true);
		$connection = \OC_DB::getConnection();

		foreach ($chunkedShareList as $subList) {
			\OC::$server->getLogger()->warning('removeSharedFolder ' . json_encode($subList), array('app' => 'files_sharing_update'));

			$statement = "UPDATE `*PREFIX*share` SET `file_target` = CASE `id` ";
			//update share table
			$ids = implode(',', array_keys($subList));
			foreach ($subList as $id => $target) {
				$statement .= "WHEN " . $connection->quote($id, \PDO::PARAM_INT) . " THEN " . $connection->quote('/Shared' . $target, \PDO::PARAM_STR);
			}
			$statement .= ' END WHERE `id` IN (' . $ids . ')';

			$query = OCP\DB::prepare($statement);

			$query->execute(array());
		}

		// set config to keep the Shared folder as the default location for new shares
		\OCA\Files_Sharing\Helper::setShareFolder('/Shared');

	}
}
