<!doctype html>
<html>
<head>
	<meta charset="UTF-8">
	<title>码农周刊抓取首页</title>
	<style>
		#content input{
			width:1000px;
		}
		#content input.newcate{
			width:100px;
		}
	</style>
</head>
<body>
	<div id="start">
		<form id="startform">
			请输入抓取期数：
			<input id="number" type="text" name="number">
			<input type="submit" value="开始">
			<button id="open">打开网页</button>
			<button id="render">输出markdown</button>
		</form>
	</div>
	<div id="content"></div>
</body>
<script src="jquery2.1.1.js"></script>
<script>
// 打开本期
$('#open').click(function(){
	window.open('http://weekly.manong.io/issues/'+$('#number').val(),'_blank');
	return false;
});

// 输出markdown
$('#render').click(function(){
	var _this=$(this);
	_this.text('处理中...');
	$.getJSON('index.php?a=render',function(data){
		if(data.res){
			window.open('readme.md','_blank');
		}else{
			alert(data.msg);
		}
		_this.text('输出markdown');
	});
	return false;
});

// 分类列表
var catelist=[];
function updateCategory(cate){
	if(catelist.indexOf(cate) < 0){
		catelist.push(cate);
		$('.add').siblings('select').append('<option value="'+cate+'">'+cate+'</option>');
	}
}

//开始
$('#startform').submit(function(e){
	$.post('index.php?a=crawl',$(this).serialize(),function(data){
		$('#content').html(data);
		// 读取分类
		$.getJSON('index.php?a=cate',function(data){
			catelist=data.cate;
			$('.add').each(function(i){
				$(this).before(data.html);
			});
		});
	});

	e.preventDefault();
});

//添加
$('#content').on('click','.add',function(e){
	var _this=$(this);
	var data=_this.parent().serialize();
	_this.text('添加中...');
	$.getJSON('index.php?a=add',data,function(rs){
		if(rs.res){
			_this.closest('.item').hide(200);
			updateCategory(rs.cate);
		}else{
			alert(rs.msg);
		}
		_this.text('添加');
	});
	e.preventDefault();
});

// 删除
$('#content').on('click','.del',function(e){
	var _this=$(this);
	var id=_this.siblings('input[name="id"]').val();
	_this.text('删除中...');
	$.getJSON('index.php?a=del',{id:id},function(rs){
		if(rs.res){
			_this.closest('.item').hide(200);
		}else{
			alert('删除失败');
		}
	});
	e.preventDefault();
});

// 打开
$('#content').on('click','.openurl',function(){
	var url=$(this).prev().val();
	window.open(url,'_blank');
	return false;
});
</script>
</html>