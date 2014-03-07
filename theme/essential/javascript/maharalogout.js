 $('.block_mnet_hosts a').click(function(e){
	e.preventDefault();
	window.open($(this).attr("href"));
});