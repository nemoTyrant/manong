<?php
/**
 * whether it is a GET request
 */
function is_get() {
	return $_SERVER['REQUEST_METHOD'] == 'GET';
}

/**
 * which action is requested
 */
function action() {
	return isset($_GET['a']) ? $_GET['a'] : 'index';
}

/**
 * show the index page
 */
function index() {
	require 'manong.php';
	exit;
}

/**
 * singleton db class
 */
function get_db() {
	static $db = null;
	if (is_null($db)) {
		$db = new manongdb();
	}
	return $db;
}

/**
 * log text
 */
$log = true;
function mylog($str) {
	global $log;
	if ($log) {
		if (is_array($str)) {
			echo "<pre>";
			print_r($str);
			echo "</pre>";
		} else {
			echo $str . '<br>';
		}
	}
}

/**
 * return json string
 */
function ajax_return($arr) {
	header('Content-type:application/json;charset=utf-8');
	echo json_encode($arr);
	exit;
}

/**
 * crawl the content
 */
function crawl() {
	$number = $_POST['number'];

	$mdb = get_db();

	$data = $mdb->get_issue($number);
	$html = '';
	if (empty($data)) {
		$html .= 'no data';
	} else {
		$cates = $mdb->get_cate();
		foreach ($data as $val) {
			// basic classification
			$cate = '';
			foreach ($cates as $v) {
				if (strlen($v) < 2) {
					// in case R or D matched
					continue;
				}
				if (false !== strpos(strtoupper($val['title']), $v)) {
					$cate = $v;
				}
			}
			$html .= '<div class="item">';
			$html .= '<hr>';
			$html .= '<form>';
			$html .= "标题：<input type='text' name='title' value='{$val['title']}'><br>";
			$html .= "描述：<input type='text' name='desc' value='{$val['desc']}'><br>";
			$html .= "地址：<input type='text' name='href' value='{$val['href']}'><button class='openurl'>打开</button><br>";
			$html .= "<input type='hidden' name='id' value='{$val['id']}'>";
			$html .= "<input type='hidden' name='number' value='{$val['number']}'>";
			$html .= "<input type='text' class='newcate' name='newcate' value='{$cate}'>";
			$html .= "<button class='add'>添加</button>";
			$html .= "<button class='del''>删除</button>";
			$html .= "</form>";
			$html .= "</div>";
		}
	}
	echo $html;
}

/**
 * load categories
 */
function cate() {
	$mdb = get_db();
	$cate = $mdb->get_cate();
	$html = '';
	$html .= '<select name="category">';
	$html .= '<option value="0">请选择</option>';
	$catearr = [];
	foreach ($cate as $val) {
		$html .= '<option value="' . $val . '">' . $val . '</option>';
		$catearr[] = $val;
	}
	$html .= '</select>';
	ajax_return(['html' => $html, 'cate' => $catearr]);
}

/**
 * add an item to db
 */
function add() {
	$cate = trim($_GET['newcate']);
	$cate = $cate ? $cate : $_GET['category'];
	$cate || ajax_return(['res' => 0, 'msg' => 'fill category']);
	$data = [
		'title' => trim($_GET['title']),
		'desc' => trim($_GET['desc']),
		'href' => trim($_GET['href']),
		'number' => trim($_GET['number']),
		'category' => mb_strtoupper($cate, 'utf-8'),
		'hash' => md5(trim($_GET['href'])),
		'addtime' => time(),
		'ctime' => time(),
	];

	if (get_db()->add($data)) {
		ajax_return(['res' => 1, 'msg' => 'success', 'cate' => $data['category']]);
	} else {
		ajax_return(['res' => 0, 'msg' => 'db error']);
	}
}

/**
 * delete an item from cache
 */
function del() {
	$id = $_GET['id'];
	$rs = get_db()->del_cache($id);
	$res = $rs ? 1 : 0;
	ajax_return(compact('res'));
}

/**
 * render data to markdown text
 */
function render() {
	$mdb = get_db();
	$data = $mdb->get_all();
	$current = $mdb->current_number();
	$readmeContent = "码农周刊分类整理
======
码农周刊的类别分的比较大，不易于后期查阅，所以我把每期的内容按语言或技术进行了分类整理。

码农周刊官方网址 [http://weekly.manong.io/](http://weekly.manong.io/)

一些不熟悉的领域分类可能不准确，请见谅

15期为图书推荐，请直接浏览[原地址](http://weekly.manong.io/issues/15)

56期为14年最受欢迎列表，请直接浏览[原地址](http://weekly.manong.io/issues/56)

现在已整理到第{$current}期。

由于现在条目过多，在同一页显示全部内容已不再合适，所以按分类写到了不同文件里。仍然想以全部内容方式查看的可以点击[all.md](category/all.md)浏览。

";

	$indexContent = "##索引\n";
	$allContent = "";

	// category index
	// ##大纲
	// [ANDROID](#ANDROID)
	// [ANGULAR](#ANGULAR)
	$categories = $mdb->get_cate();
	if ($categories) {
		$readmeContent .= "##索引\n";
		foreach ($categories as $val) {

			// 全文页的索引
			$indexContent .= "[{$val}](#{$val})  \n";

			// 首页的索引
			$filename = str_replace(['.', ' ', '/'], ['', '_', '_'], $val);
			$readmeContent .= "[{$val}](category/{$filename}.md)  \n";
		}
	}

	$current_cate = ''; // 当前分类
	$current_file = ''; // 当前写入文件
	$file_content = ''; // 当前写入文件内容
	foreach ($data as $val) {
		if ($val['category'] != $current_cate) {
			$allContent .= "\n";
			$allContent .= "<a name=\"{$val['category']}\"></a>\n";
			$allContent .= "##" . str_replace('#', '\# ', $val['category']) . "\n";
			$current_cate = $val['category'];

			// 将上一个类别的内容写入文件
			if (!empty($file_content)) {
				file_put_contents($current_file, $file_content);
				$file_content = '';
			}

			$current_file = 'category/' . str_replace(['.', ' ', '/'], ['', '_', '_'], $val['category']) . '.md';
		}
		$allContent .= "[{$val['title']}]({$val['href']})  \n";
		$file_content .= "[{$val['title']}]({$val['href']})  \n";
	}

	$rs = file_put_contents('./readme.md', $readmeContent); // 写入readme.md
	file_put_contents('./category/all.md', $indexContent . $allContent); // 全部内容
	if ($rs) {
		ajax_return(['res' => 1]);
	} else {
		ajax_return(['res' => 0, 'msg' => '输出失败']);
	}
}

class manongdb {
	private $pdo;
	private $issue_url = 'http://weekly.manong.io/issues/';

	function __construct($user = 'root', $password = '', $dbname = 'manong') {
		$this->pdo = new PDO('mysql:host=localhost;dbname=' . $dbname, $user, $password);
		$this->pdo->query('set names utf8');
	}

	/**
	 * get issue data
	 */
	function get_issue($number) {
		mylog('searching for cache');
		$data = $this->cache($number);
		if (empty($data)) {
			mylog('no cache,start crawling');
			$data = $this->crawl($number);
			if (false === $data) {
				die('抓取失败');
			}
		}
		return $data;
	}

	/**
	 * get issue cache
	 */
	function cache($number) {
		$data = $this->pdo->query("select * from cache where number={$number}")->fetchAll(PDO::FETCH_ASSOC);
		if ($data) {
			mylog('cache founded');
		}
		return $data;
	}

	/**
	 * delete an item from cache
	 */
	function del_cache($id) {
		return $this->pdo->exec("delete from cache where id={$id}");
	}

	/**
	 * crawl the data
	 */
	function crawl($number) {
		$url = $this->issue_url . $number;
		$content = file_get_contents($url);
		$content = str_replace(["\r", "\n"], '', $content);
		if ($number >= 91) {
			$pattern = '/<h4><a target="_blank" href="(.*?)">(.*?)<\/a>&nbsp;&nbsp;(?:<a target="_blank".*?<\/a>)?<\/h4>.*?<p>(.*?)<\/p>/';
		} else {
			$pattern = '/<h4><a target="_blank" href="(.*?)">(.*?)<\/a>&nbsp;&nbsp;<\/h4>.*?<p>(.*?)<\/p>/';
		}
		$rs = preg_match_all($pattern, $content, $matches);
		if ($rs) {
			mylog('crawl finished.start parsing');
			$data = array();
			foreach ($matches[1] as $key => $val) {
				if (false !== strpos($matches[1][$key], 'job') || false !== strpos($matches[1][$key], 'amazon')) {
					// get rid of job and book recommendations
					continue;
				}
				$item = [
					'title' => strip_tags($matches[2][$key]),
					'desc' => $matches[3][$key],
					'href' => $val,
					'number' => $number,
					'hash' => md5($val),
				];
				//加入数据库
				$id = $this->add_cache($item);
				$item['id'] = $id;
				mylog("item {$id} added to cache");
				// 加入数组
				$data[] = $item;
			}
			return $data;
		}
		mylog('failed to crawl');
		return false;
	}

	/**
	 * add item to cache
	 */
	function add_cache($item) {
		$sql = $this->arr2sql('cache', $item);
		$this->pdo->exec($sql);
		$id = $this->pdo->lastInsertId();
		return $id;
	}

	/**
	 * translate array to sql
	 */
	function arr2sql($dbname, $arr, $updateid = null) {

		if ($updateid) {
			// return "update {$dbname} set "
			unset($arr['ctime']);
			unset($arr['addtime']);
			$sql = "update {$dbname} set ";
			foreach ($arr as $key => $val) {
				$sql .= "`{$key}`='{$val}',";
			}
			$sql .= 'ctime=' . time();
			$sql .= " where id={$updateid}";
			return $sql;
		} else {
			$fields = [];
			$values = [];
			foreach ($arr as $key => $v) {
				$fields[] = "`{$key}`";
				$values[] = "'{$v}'";
			}
			return "insert into {$dbname} (" . implode(',', $fields) . ") values(" . implode(',', $values) . ")";
		}
	}

	/**
	 * get categories
	 */
	function get_cate() {
		$arr = [];
		$rs = $this->pdo->query("select distinct category from issue order by category")->fetchAll(PDO::FETCH_ASSOC);
		foreach ($rs as $val) {
			$arr[] = $val['category'];
		}
		return $arr;
	}

	/**
	 * add an item to db or update it if exists
	 */
	function add($data) {
		$sql = $this->arr2sql('issue', $data);
		$rs = $this->pdo->exec($sql);
		if ($rs) {
			return $this->pdo->lastInsertId();
		}
		$rs = $this->pdo->query("select id from issue where hash='{$data['hash']}'")->fetch(PDO::FETCH_ASSOC);
		if (isset($rs['id'])) {
			$update = $this->pdo->exec($this->arr2sql('issue', $data, $rs['id']));
			if ($update) {
				return $rs['id'];
			}

		}
		return false;
	}

	/**
	 * fetch all recorded data
	 */
	function get_all() {
		return $this->pdo->query('select * from issue order by category,addtime')->fetchAll(PDO::FETCH_ASSOC);
	}

	function current_number() {
		$rs = $this->pdo->query('select max(number) as max from issue')->fetch(PDO::FETCH_ASSOC);
		return $rs['max'];
	}
}
?>