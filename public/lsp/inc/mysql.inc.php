<?php

$DB_HOST = "localhost";
$DB_USER = "someuser";
$DB_PASS = "P@SSWORD";
$DB_DATABASE = "somedatabase";
$PAGE_SIZE = 10;

require_once('config.inc.php');

function connectdb()
{
	global $DB_HOST, $DB_USER, $DB_PASS, $DB_DATABASE;
	// FIXME: TODO:  Change to use mysqli instead, these are deprecated
	@mysql_connect( $DB_HOST, $DB_USER, $DB_PASS );
	mysql_select_db( $DB_DATABASE );
}

function get_object_by_id( $table, $id, $field, $id_field = "id" )
{
	connectdb();
	$q = sprintf( "SELECT %s AS obj FROM `%s` WHERE `%s`='%s'",
				/*mysql_real_escape_string(*/ $field/* )*/,
				mysql_real_escape_string( $table ),
				mysql_real_escape_string( $id_field ),
				mysql_real_escape_string( $id ) );
	$result = mysql_query( $q );
	if( mysql_num_rows( $result ) > 0 )
	{
		$object = mysql_fetch_object( $result );
		mysql_free_result ($result);
		return $object->obj;
	}
	return( FALSE );
}


function get_id_by_object( $table, $field, $obj )
{
	connectdb();
	$q = sprintf( "SELECT id FROM `%s` WHERE `%s` LIKE '%s'",
				mysql_real_escape_string( $table ),
				mysql_real_escape_string( $field ),
				mysql_real_escape_string( $obj ) );
	$result = mysql_query( $q );
	if( mysql_num_rows( $result ) > 0 )
	{
		$object = mysql_fetch_object( $result );
		mysql_free_result ($result);
		return $object->id;
	}
	return( -1 );
}


function rebuild_query_string( $key, $value )
{
	$old = $_GET[$key];
	$_GET[$key] = $value;
	$qs = array();
	foreach($_GET as $k => $v)
	{
		array_push( $qs, $k."=".$v );
	}
	$_GET[$key] = $old;
	return( implode( "&amp;", $qs ) );
}

function file_show_query_string()
{
	return( 'action=show&file='.$_GET["file"] );
}


function get_latest()
{
	global $PAGE_SIZE;
 	connectdb();
	$req = "SELECT files.id, licenses.name AS license,size,realname,filename,users.login,".
		"categories.name AS category,subcategories.name AS subcategory,".
		"insert_date,update_date,description,files.downloads AS downloads FROM files ".
		"INNER JOIN categories ON categories.id=files.category ".
		"INNER JOIN subcategories ON subcategories.id=files.subcategory ".
		"INNER JOIN users ON users.id=files.user_id ".
		"INNER JOIN licenses ON licenses.id=files.license_id ".
	 	"ORDER BY files.update_date DESC LIMIT ". $PAGE_SIZE;
 	$result = mysql_query ($req);

 	echo "<h2>Latest entries</h2>".mysql_error()."\n";
	echo "<table style=\"border:none;\">\n";
	while ($object = mysql_fetch_object ($result))
	{
		show_basic_file_info( $object, TRUE );
	}
	echo'</table>';
	mysql_free_result ($result);
}



function password_match ($pass,$user)
 {
 	connectdb ();
	$q = sprintf( "SELECT login FROM users WHERE password LIKE SHA1('%s') AND login LIKE '%s' AND loginFailureCount<6",
				mysql_real_escape_string( $pass ),
				mysql_real_escape_string( $user ) );
	$result = mysql_query( $q );
 	$object = mysql_fetch_object ($result);
 	mysql_free_result ($result);
 	if($object->login)
	{
		$q = sprintf( "UPDATE users SET loginFailureCount=0 WHERE login LIKE '%s'",
				mysql_real_escape_string( $user ) );
		$result = mysql_query( $q );
		return true;
	}
	else
	{
		$q = sprintf( "UPDATE users SET loginFailureCount=loginFailureCount+1 WHERE login LIKE '%s'",
				mysql_real_escape_string( $user ) );
		$result = mysql_query( $q );
	}
	return false;
 }


function mydate()
{
 	return date ("Y-m-d",time ());
}

function myis_admin ($uid)
{
	return( get_object_by_id( "users", $uid, "is_admin" ) );
}
 

function myadd_user ($login,$realname,$pass,$is_admin)
{
 	connectdb ();
	$q = sprintf( "INSERT INTO users(login,realname,password,is_admin) VALUES ('%s','%s',SHA1('%s'),'%s')",
				mysql_real_escape_string( $login ),
				mysql_real_escape_string( $realname ),
				mysql_real_escape_string( $pass ),
				mysql_real_escape_string( $is_admin ) );
 	mysql_query( $q );
 	
}

function mychange_user ($login,$realname,$pass)
{
 	connectdb ();
	if($pass!='')
	{
		$q = sprintf( "UPDATE users SET `realname`='%s', `password`=SHA1('%s') WHERE `login` LIKE '%s'",
					mysql_real_escape_string( $realname ),
					mysql_real_escape_string( $pass ),
					mysql_real_escape_string( $login ) );
	}
	else
	{
		$q = sprintf( "UPDATE users SET `realname`='%s' WHERE `login` LIKE '%s'",
					mysql_real_escape_string( $realname ),
					mysql_real_escape_string( $login ) );
	}
 	mysql_query( $q );
 	
 }



function get_user_id( $login )
{
	return get_id_by_object( "users", "login", $login );
}
 
 
function get_user_realname( $login )
{
	return get_object_by_id( "users", get_user_id( $login ), 'realname' );
}
 
 


function get_file_name( $fid )
{
	return( get_object_by_id( "files", $fid, "filename" ) );
}
 
function get_file_owner( $fid )
{
	return( get_object_by_id( "files", $fid, "user_id" ) );
}

function get_file_description( $fid )
{
	return( get_object_by_id( "files", $fid, "description" ) );
}
 
function get_file_license( $fid )
{
	return( get_object_by_id( "files", $fid, "license_id" ) );
}
 


function get_category_id( $cat )
{
	return( get_id_by_object( "categories", "name", $cat ) );
}
 
function get_subcategory_id( $cat )
{
	return( get_id_by_object( "subcategories", "name", $cat ) );
}

 
function get_categories()
{
	global $LSP_URL;
	connectdb();
	$result = mysql_query(
		'SELECT categories.name AS name, COUNT(files.id) AS cnt FROM categories '.
		'LEFT JOIN files ON files.category = categories.id '.
		'GROUP BY categories.name '.
		'ORDER BY categories.name ');
echo mysql_error();

	while( $object = mysql_fetch_object( $result ) )
	{
		echo "<a class='category' href='".htmlentities ($LSP_URL."?action=browse&category=".$object->name)."'>".
			$object->name." <span class='count'>(".$object->cnt.")</span></a>";
		if( isset( $_GET["category"] ) && $_GET["category"] == $object->name )
		{
			$cat = $_GET["category"];
			$catid = get_category_id( $object->name );
//			$res2 = mysql_query( "SELECT name FROM subcategories WHERE category='".$catid."'" );
			$res2 = mysql_query( 
				"SELECT subcategories.name AS name, COUNT(files.id) AS cnt FROM subcategories ".
				"LEFT JOIN files ON files.subcategory = subcategories.id AND files.category='$catid' ".
				"WHERE subcategories.category='$catid' ". 
				"GROUP BY subcategories.name ".
				"ORDER BY subcategories.name ");
			echo "<div class='selected'>";
	echo mysql_error();
			while( $object2 = mysql_fetch_object( $res2 ) )
			{
				echo "<a class='subcategory";
                                if( $object2->name == $_GET["subcategory"] )
                                {
                                        echo " selected";
                                }
				echo "' href=\"".htmlentities ($LSP_URL."?action=browse&category=$cat&subcategory=".$object2->name)."\"> ";
				echo $object2->name." <span class='count'>(".$object2->cnt.")</span></a>";
			}
			mysql_free_result( $res2 );
			echo "</div>";
		}
	}
	mysql_free_result( $result );
}




function get_categories_for_ext( $ext, $default = "" )
{
	$cats = '';
	connectdb();
	$result = mysql_query( 'SELECT categories.name AS catname, subcategories.name AS subcatname FROM filetypes INNER JOIN categories ON categories.id=filetypes.category INNER JOIN subcategories ON subcategories.category=categories.id WHERE extension LIKE \''.mysql_real_escape_string( $ext ).'\' ORDER BY categories.name, subcategories.name' );
	if( mysql_num_rows( $result ) > 0 )
	{ 
		while( $object = mysql_fetch_object( $result ) )
		{
			$fullname = $object->catname.'-'.$object->subcatname;
			if( $fullname == $default )
			{
				$def = ' selected';
			}
			else
			{
				$def = '';
			}
			$cats .= '<option'.$def.'>'.$fullname.'</option>'."\n";
		}
		mysql_free_result( $result );
		return( $cats );
	}
	return( FALSE );
}



function get_license_id( $license )
{
	return( get_id_by_object( "licenses", "name", $license ) );
}

function get_license_name( $lid )
{
	return( get_object_by_id( "licenses", $lid, "name" ) );
}


function get_licenses( $default = "" )
{
	connectdb();
	$result = mysql_query( 'SELECT name FROM licenses' );

	while( $object = mysql_fetch_object( $result ) )
	{
		if( $object->name == $default )
		{
			$def = ' selected';
		}
		else
		{
			$def = '';
		}
		echo '<option'.$def.'>'.$object->name.'</option>'."\n";
	}
	mysql_free_result( $result );
}




function get_comment_count( $fid )
{
	return( get_object_by_id( "comments", $fid, "COUNT(*)", "file_id" ) );
}



function get_comments( $fid )
{
	global $LSP_URL;
	connectdb ();
	$q = sprintf( "SELECT users.realname,users.login,date,text FROM comments INNER JOIN users ON users.id=comments.user_id WHERE file_id='%s' ORDER BY date", $fid );
	$result = mysql_query( $q );
	$out = '';
 	while( $object = mysql_fetch_object( $result ) )
 	{
		$name = '<i>'.$object->login.'</i>';
		if( $_SESSION["remote_user"] == $object->login )
		{
			$name = "You";
		}
		else if( strlen( $object->realname ) > 0 )
		{
			$name = '<a href="'.$LSP_URL.'?action=browse&amp;user='.$object->login.'">'.$object->realname.' ('.$object->login.')</a>';
		}
  		$out .= '<hr /><p>'.$name.' wrote on '.$object->date.'</p><p>'.htmlspecialchars($object->text, ENT_COMPAT, 'UTF-8')."</p>\n";
 	}
	if( strlen( $out ) )
	{
		echo "<b>Comments:</b>".$out."<br />\n";
	}
	else
	{
		echo "<b>No comments yet</b><br />\n";
	}
	mysql_free_result( $result );
}



function get_file_category( $fid )
{
 	connectdb();
 	$q = sprintf( "SELECT categories.name FROM files INNER JOIN categories ON categories.id=files.category WHERE files.id='%s'", mysql_real_escape_string( $fid ) );
 	$result = mysql_query( $q );
 	$object = mysql_fetch_object( $result );
 	mysql_free_result( $result );
 	return $object->name;
}

function get_file_subcategory( $fid )
{
 	connectdb();
 	$q = sprintf( "SELECT subcategories.name FROM files INNER JOIN subcategories ON subcategories.id=files.subcategory WHERE files.id='%s'", mysql_real_escape_string( $fid ) );
 	$result = mysql_query( $q );
 	$object = mysql_fetch_object( $result );
 	mysql_free_result( $result );
 	return $object->name;
}


function get_results( $cat, $subcat, $sort = '', $search = '' )
{
	global $PAGE_SIZE;
	$page = $_GET["page"];
	connectdb();

	if(strlen( $cat ) > 0 )
	{	
		# Where clause for count and query
		$where= sprintf( "WHERE categories.name='%s' ", mysql_real_escape_string( $cat ) );
		if( strlen( $subcat ) > 0 )
		{
			$where .= sprintf( "AND subcategories.name='%s' ", mysql_real_escape_string( $subcat ) );
		}
	}
	if( strlen($search) > 0 )
	{
		if( strlen($where) == 0 )
		{
			$where = "WHERE files.filename = files.filename ";
		}
		$where .= "AND ( files.filename LIKE '%$search%' OR users.login LIKE '%$search%' OR users.realname LIKE '%$search%') ";
	}

	# Get count
	$count = mysql_result(mysql_query(
		"SELECT COUNT(files.id) FROM files ".
		"INNER JOIN categories ON categories.id=files.category ".
		"INNER JOIN subcategories ON subcategories.id=files.subcategory ".
		"INNER JOIN users ON users.id=files.user_id ".
		$where), 0, 0);

	if( $count > 0 )
	{
		$req = "SELECT files.id, licenses.name AS license,size,realname,filename,users.login,categories.name AS category,subcategories.name AS subcategory,";
		$req .= "files.downloads*files.downloads/(UNIX_TIMESTAMP(NOW())-UNIX_TIMESTAMP(files.insert_date)) AS downloads_per_day,";
		$req .= "files.downloads AS downloads,";
		$req .= "insert_date,update_date,description,AVG(ratings.stars) as rating FROM files ";
		$req .= "INNER JOIN categories ON categories.id=files.category ";
		$req .= "INNER JOIN subcategories ON subcategories.id=files.subcategory ";
		$req .= "INNER JOIN users ON users.id=files.user_id ";
		$req .= "INNER JOIN licenses ON licenses.id=files.license_id ";
		$req .= "LEFT JOIN ratings ON ratings.file_id=files.id ";
		$req .= $where;
		$req .= "GROUP BY files.id ";
		if( $sort == 'downloads' )
		{
			$req .= "ORDER BY downloads_per_day DESC ";
		}
		else if( $sort == 'rating' )
		{
			$req .= "ORDER BY rating DESC,COUNT(ratings.file_id) DESC ";
		}
		else
		{
			$req .= "ORDER BY files.insert_date DESC ";
		}
		$req .= sprintf("LIMIT %d,%d", $page*$PAGE_SIZE, $PAGE_SIZE);
		$result = mysql_query ($req);

		echo "<br /><table style=\"border:none; width:100%;\">\n";
		while( $object = mysql_fetch_object ($result) )
		{
			show_basic_file_info( $object, TRUE );
		}
		echo'</table>';

		$pages = $count / $PAGE_SIZE;
		if ($pages>1) {
			echo "<b>";
			for($j=0; $j < $count / $PAGE_SIZE; ++$j )
			{
				if($j==$page)
				{
					echo $j+1;
				}
				else
				{
					echo "<a href=\"".$LSP_URL."?action=browse&amp;category=$cat&amp;subcategory=$subcat&amp;page=$j&amp;sort=$sort\">".($j+1)."</a>";
				}
				echo "&nbsp;&nbsp;\n";
			}
			echo "</b>";
		}
		echo "<br />\n";
		mysql_free_result( $result );
	}
	else
	{
		echo '<br /><h3>No files were submitted in this category yet.</h3>';
	}
}


function show_user_content( $user )
{
	$uid = get_user_id( $user );
	if( $uid >= 0 )
	{
		connectdb ();
		$req = "SELECT files.id, licenses.name AS license,size,realname,filename,users.login,categories.name AS category,subcategories.name AS subcategory,";
		$req .= "insert_date,update_date,description FROM files ";
		$req .= "INNER JOIN categories ON categories.id=files.category ";
		$req .= "INNER JOIN subcategories ON subcategories.id=files.subcategory ";
		$req .= "INNER JOIN users ON users.id=files.user_id ";
		$req .= "INNER JOIN licenses ON licenses.id=files.license_id ";
		$req .= "WHERE files.user_id='".mysql_real_escape_string( $uid )."' ";
		$req .= "ORDER BY files.insert_date DESC";
		$result = mysql_query ($req);

		if( $result != FALSE && mysql_num_rows( $result ) > 0 )
		{
			echo '<h2>All content submitted by <i>'.get_user_realname( $user ).' '.$user.'</i></h2>';
			echo "<br /><table style=\"border:none;\">\n";
			while( $object = mysql_fetch_object( $result ) )
			{
				show_basic_file_info( $object, TRUE, FALSE );
			}
			echo'</table>';
			echo "<br />\n";
			mysql_free_result ($result);
		}
		else
		{
			if( isset( $_SESSION["remote_user"] ) && $user == $_SESSION["remote_user"] )
			{
				$user = "</i>You<i>";
			}
			echo '<h2><i>'.$user.'</i> did not submit any content yet!</h2>';
		}
	}
	else
	{
		echo '<h2 style="color:#f60">User "'.$user.'" not found!</h2>';
	}
}


function insert_category ($fext,$cat)
 {
  connectdb ();
  $req = "SELECT count(name) FROM categories WHERE name LIKE '".$cat."' AND filetypes_extension LIKE '".$fext."'";
  $result = mysql_query ($req);
  $row = mysql_fetch_row ($result);
  if (!$row[0])
   {
  mysql_free_result ($result);
  $req = "INSERT INTO categories (name,filetypes_extension) VALUES ('".$cat."','".$fext."')";
  return mysql_query ($req);
   } else return 0;
  }


function show_basic_file_info( $f, $browsing_mode = FALSE, $show_author = TRUE )
{
	global $LSP_URL;
	echo "<tr class=\"file\"><td style=\"width:60%\"><div style=\"overflow: hidden\" >\n";
	if( $browsing_mode )
	{
		echo '<div><a href="'.htmlentities ($LSP_URL.'?action=show&file='.$f->id).'" style="font-weight:bold; font-size:1.15em" title="'.$f->filename.'">'.$f->filename.'</a></div>';
		echo '<a href="'.htmlentities ($LSP_URL.'?action=browse&category='.$f->category).'">'.$f->category.'</a>&nbsp;<span class="fa fa-caret-right lsp-caret-right-small"></span>&nbsp;<a href="'.htmlentities ($LSP_URL.'?action=browse&category='.$f->category.'&subcategory='.$f->subcategory).'">'.$f->subcategory.'</a><br />';
	}
	if( $show_author )
	{
		echo 'by <a href="'.$LSP_URL.'?action=browse&amp;user='.$f->login.'">'.$f->realname." (".$f->login.")</a><br />\n";
	}

	if( $browsing_mode == FALSE )
	{
		$hr_size = round( $f->size / 1024 )." KB";
		echo "<b>Size:</b> ".$hr_size."<br />\n";
		echo "<b>License:</b> ".$f->license."<br />\n";
	}
	echo "</div></td><td style=\"width:20px;\"></td><td>\n";
	if( $browsing_mode )
	{
		echo "<b>Date:</b> ".$f->update_date."<br />\n";
		echo "<b>Popularity:</b> ".$f->downloads." downloads, ".get_comment_count($f->id)." comments<br />\n";
	}
	else
	{
		echo '<div class="nobr"><b>Submitted:</b> '.$f->insert_date.'</div>';
		echo "<b>Updated:</b> ".$f->update_date."<br />\n";
	}
	echo "<b>Rating:</b> ";

	$rating = get_file_rating( $f->id );
	for( $i = 1; $i <= $rating ; ++$i )
	{
		echo '<span class="fa fa-star lsp-star"></span>';
	}
	for( $i = $rating+1; floor( $i )<=5 ; ++$i )
	{
		echo '<span class="fa fa-star-o lsp-star-o"></span>';
	}
	echo ' ('.round(20*$rating).'%, '.get_file_rating_count( $f->id ).' votes)';
	echo'</td></tr><tr><td><br /></td></tr>';
}




function show_file( $fid, $user )
{
	connectdb();
	$req = "SELECT licenses.name AS license,size,realname,filename,users.login,categories.name AS category,subcategories.name AS subcategory,";
	$req .= "insert_date,update_date,description,downloads,files.id FROM files ";
	$req .= "INNER JOIN categories ON categories.id=files.category ";
	$req .= "INNER JOIN subcategories ON subcategories.id=files.subcategory ";
	$req .= "INNER JOIN users ON users.id=files.user_id ";
	$req .= "INNER JOIN licenses ON licenses.id=files.license_id ";
	$req .= sprintf( "WHERE files.id=%s", mysql_real_escape_string( $fid ) );

	$res = mysql_query( $req );

	if( mysql_num_rows( $res ) < 1 )
	{
		echo '<h2 style="color:#f60;">File not found</h2>';
		return;
	}
	$f = mysql_fetch_object( $res );

	$img = '&nbsp;<span class="fa fa-caret-right lsp-caret-right"></span>&nbsp;';
	echo '<h2>'.$f->category.$img.$f->subcategory.$img.$f->filename.'</h2>'."\n";
	echo '<div id="filedetails">';

	echo "<table style=\"border:none;\">\n";
	show_basic_file_info( $f, FALSE );
	echo'</table>';

	echo "<b>Downloads:</b> ".$f->downloads."<br />\n";
	if($f->description != '')
	{
		echo "<p/><b>Description:</b><br />\n";
		echo str_replace("\n","<br />\n",$f->description)."<br />\n";
	}
	echo "<br /><table border=\"0\"><tr><td>\n";
	$url = htmlentities( 'lsp_dl.php?file='.$fid.'&name='.$f->filename );
	echo '<a href="'.$url.'" id="downloadbtn"><span class="fa fa-download lsp-download"></span>&nbsp;Download</a>';
	echo '</td><td style="width:50px"></td><td>';
    
	if( isset( $_SESSION["remote_user"] ) )
	{
		echo '<a href="'.htmlentities( $LSP_URL.'?comment=add&file='.$fid ).'"><span class="fa fa-comment lsp-comment"></span>&nbsp;Add comment</a><br />';
	}
	else
	{
		echo "<b>You need to login in order to write comments, rate or edit this content (if you're the author of it).</b>\n";
	}
	if ($f->login == $user || myis_admin( get_user_id( $user ) ) )
	{
		echo '<a href="'.htmlentities ($LSP_URL.'?content=update&file='.$fid).'"><span class="fa fa-edit lsp-edit"></span>&nbsp;Edit</a><br />';
		echo '<a href="'.htmlentities ($LSP_URL.'?content=delete&file='.$fid).'"><span class="fa fa-remove lsp-remove"></span>&nbsp;Delete</a> ';
	}
	if (isset ($_SESSION["remote_user"]))
	{
		$urating = get_user_rating( $fid, $_SESSION["remote_user"] );
		echo'</td><td style="width:30px;"></td><td><b>Rating:</b></td><td style="padding-left:8px;line-height:14px;">';
		for( $i = 1; $i < 6; ++$i )
		{
			echo '<a href="'.htmlentities($LSP_URL.'?'.file_show_query_string().'&rate='.$i ).'" class="ratelink" ';
			if( $urating == $i )
			{
				echo 'style="border:1px solid #88f;"';
			}
			echo '>';
			for( $j = 1; $j <= $i ; ++$j )
			{
				echo '<span class="fa fa-star lsp-star">';
			}
			echo '</a><br />';
		}
	}
	echo'</td></tr></table>';
	echo "<br />\n";

	get_comments( $fid );

	echo '</div>'."\n";

	mysql_free_result ($res);	

}



function get_user_rating( $fid, $user )
{
	$uid = get_user_id($user);
	if( $uid >= 0 )
	{
		connectdb ();
		$q = sprintf( "SELECT COUNT(stars) AS cnt FROM ratings WHERE `file_id`='%s' AND `user_id`='%s'", mysql_real_escape_string( $fid ), mysql_real_escape_string( $uid ) );
		$result = mysql_query( $q );
		$object = mysql_fetch_object ($result);
		mysql_free_result ($result);
		if( $object->cnt < 1 )
		{
			return( 0 );
		}

		
		$q = sprintf( "SELECT stars FROM ratings WHERE `file_id`='%s' AND `user_id`='%s'", mysql_real_escape_string( $fid ), mysql_real_escape_string( $uid) );
		$result = mysql_query( $q );

		$object = mysql_fetch_object ($result);
		mysql_free_result ($result);
		return $object->stars;
	}
}

function update_rating( $fid, $stars, $user )
{
	if( $stars < 1 || $stars > 5 )
	{
		echo "invalid";
		return;
	}
	$uid = get_user_id($user);
	if( $uid >= 0 )
	{
		if( get_user_rating( $fid, $user ) > 0 )
		{
	 		$req = sprintf( "UPDATE ratings SET `stars`='%s' WHERE `file_id`='%s' AND `user_id`='%s'",
						mysql_real_escape_string( $stars ),
						mysql_real_escape_string( $fid ),
						mysql_real_escape_string( $uid ) );
		}
		else
		{
	 		$req = sprintf( "INSERT INTO ratings(file_id,user_id,stars) VALUES('%s', '%s', '%s' )",
						mysql_real_escape_string( $fid ),
						mysql_real_escape_string( $uid ),
						mysql_real_escape_string( $stars ) );
		}
	 	connectdb();
	 	mysql_query ($req);
	}
}


function get_file_rating_count( $fid )
{
	return( get_object_by_id( "ratings", $fid, "COUNT(*)", "file_id" ) );
}


function get_file_rating( $fid )
{
	return( get_object_by_id( "ratings", $fid, "AVG(stars)", "file_id" ) );
}


function insert_file( $filename, $uid, $catid, $subcatid, $licenseid, $description, $size, $sum, &$id )
{
	connectdb();
	$req = "INSERT INTO files (filename,user_id,insert_date,update_date,".
		"category,subcategory,license_id,description,size,hash) ";
	$req .= "VALUES ('".mysql_real_escape_string( $filename )."','".
		mysql_real_escape_string( $uid )."',".
		"(SELECT NOW() ),".
		"(SELECT NOW() ),'".
		mysql_real_escape_string( $catid )."','".
		mysql_real_escape_string($subcatid)."',".
		mysql_real_escape_string($licenseid).",'".
		mysql_real_escape_string(htmlspecialchars($description))."',".
		mysql_real_escape_string( $size ).",'".
		"$sum')";
	$ret = mysql_query( $req );
	$id = mysql_insert_id();
	return( $ret );
}




function update_file($fid, $catid, $subcatid, $licenseid, $description )
{

	connectdb ();
	if( get_user_id( $_SESSION["remote_user"] ) != get_object_by_id( "files", $fid, "user_id" ) )
	{
		return;
	}

	$req = "UPDATE files SET `category`='".mysql_real_escape_string($catid)."',`subcategory`='".mysql_real_escape_string($subcatid)."',`license_id`='".mysql_real_escape_string($licenseid)."',`description`='".mysql_real_escape_string(htmlspecialchars($description))."',`update_date`=(SELECT NOW()) ";
	$req .= sprintf( "WHERE `id`='%s'", mysql_real_escape_string( $fid ) );
	return mysql_query( $req );
}

function increment_file_downloads ($fid)
{
	connectdb ();
	$req = sprintf( "UPDATE files SET downloads=downloads+1 WHERE `id`='%s'",
						mysql_real_escape_string( $fid ));
	mysql_query( $req );

	//$req = sprintf( "SELECT UNCOMPRESS(data) AS data FROM files WHERE `id`='%s'", mysql_real_escape_string($fid));
	//$result = mysql_query ($req);
	//$object = mysql_fetch_object ($result);
	//mysql_free_result ($result);

	return "";
}



function delete_file( $fid )
{
	connectdb();
	$fid = mysql_real_escape_string( $fid );
	if( mysql_query( sprintf( "DELETE FROM files WHERE `id`='%s'", $fid ) ) )
	{
		mysql_query( sprintf( "DELETE FROM comments WHERE `file_id`='%s'", $fid ) );
		mysql_query( sprintf( "DELETE FROM ratings WHERE `file_id`='%s'", $fid ) );
		return( TRUE );
	}
	return( FALSE );
}



function add_visitor_comment( $file, $comment, $user)
{
	$uid = get_user_id( $user );
	$comment = htmlspecialchars($comment, ENT_COMPAT, 'UTF-8' );

	if( $uid >= 0 )
	{
	 	connectdb();
	 	$req = sprintf( "INSERT INTO comments (user_id,file_id,text) VALUES('%s', '%s', '%s')",
					mysql_real_escape_string( $uid ),
					mysql_real_escape_string( $file ),
					mysql_real_escape_string( $comment ) );
	 	mysql_query( $req );
	}
}

?>
