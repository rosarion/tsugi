<?php

use \Tsugi\Core\LTIX;

LTIX::getConnection();

    if ( !isset($maxversion) ) $maxversion = 0;
    if ( !isset($maxpath) ) $maxpath = '';

    // Check to see if the tables need to be created
    if ( isset($DATABASE_INSTALL) && $DATABASE_INSTALL !== false ) {
        foreach ( $DATABASE_INSTALL as $entry ) {
            echo("-- Checking table ".$entry[0]."<br/>\n");
            $table_fields = $PDOX->metadata($entry[0]);
            if ( $table_fields === false ) {
                echo("-- Creating table ".$entry[0]."<br/>\n");
                error_log("-- Creating table ".$entry[0]);
                $q = $PDOX->queryReturnError($entry[1]);
                if ( ! $q->success ) die("Unable to create ".$entry[1]." ".$q->errorImplode."<br/>".$entry[1] );
                $OUTPUT->togglePre("-- Created table ".$entry[0], $entry[1]);
                $sql = "INSERT INTO {$plugins}
                    ( plugin_path, version, created_at, updated_at ) VALUES
                    ( :plugin_path, :version, NOW(), NOW() )
                    ON DUPLICATE KEY
                    UPDATE version = :version, updated_at = NOW()";
                $values = array( ":plugin_path" => $path,
                        ":version" => $CFG->dbversion);
                $q = $PDOX->queryReturnError($sql, $values);
                if ( ! $q->success ) die("Unable to set version for ".$path." ".$q->errorimplode."<br/>".$entry[1] );
                // Do the POST-Create
                if ( isset($DATABASE_POST_CREATE) && $DATABASE_POST_CREATE !== false ) {
                    $DATABASE_POST_CREATE($entry[0]);
                }
            }
        }
    } else {
        echo("-- Set version for ".$path."<br/>");
        error_log("-- Set version for ".$path);
        $sql = "INSERT INTO {$plugins}
            ( plugin_path, version, created_at, updated_at ) VALUES
            ( :plugin_path, :version, NOW(), NOW() )
            ON DUPLICATE KEY
            UPDATE version = :version, updated_at = NOW()";
        $values = array( ":plugin_path" => $path,
                ":version" => $CFG->dbversion);
        $q = $PDOX->queryReturnError($sql, $values);
        if ( ! $q->success ) die("Unable to set version for ".$path." ".$q->errorimplode."<br/>".$entry[1] );
    }

    // Check to see if there is any upgrading needed
    if ( isset($DATABASE_UPGRADE) && $DATABASE_UPGRADE !== false ) {
        $sql = "SELECT version FROM {$plugins} WHERE plugin_path = :plugin_path";
        $values = array( ":plugin_path" => $path);
        $q = $PDOX->queryReturnError($sql, $values);
        $version = $CFG->dbversion;
        if ( $q->success ) {
            $data = $q->fetch(PDO::FETCH_ASSOC);
            if ( is_array($data) && isset($data['version']) ) $version = $data['version']+0;
        }
        echo("-- Current data model version $version <br/>\n");
        $newversion = $DATABASE_UPGRADE($version);
        if ( $newversion > $maxversion ) {
            $maxversion = $newversion;
            $maxpath = $path;
        }
        if ( $newversion > $CFG->dbversion ) {
            echo("-- WARNING: Database version=$newversion for $path higher than
                \$CFG->dbversion=$CFG->dbversion in setup.php<br/>\n");
        }
        if ( $newversion > $version ) {
            echo("-- Upgraded to data model version $newversion <br/>\n");
            $sql = "INSERT INTO {$plugins}
                ( plugin_path, version, created_at, updated_at ) VALUES
                ( :plugin_path, :version, NOW(), NOW() )
                ON DUPLICATE KEY
                UPDATE version = :version, updated_at = NOW()";
            $values = array( ":version" => $newversion, ":plugin_path" => $path);
            $q = $PDOX->queryReturnError($sql, $values);
            if ( ! $q->success ) die("Unable to update version for ".$path." ".$q->errorimplode."<br/>".$entry[1] );
        }
    }

    // Make sure these do not run twice
    unset($DATABASE_INSTALL);
    unset($DATABASE_POST_CREATE);
    unset($DATABASE_UNINSTALL);
    unset($DATABASE_UPGRADE);

