<?php 
#~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
#
# initiate the class: $divLoader = new loadingDiv;
# call the function after the <body> tag: $divLoader->loader();
#
#~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

class loadingDiv {

	var $width = 300;
	var $height = 300;
	var $loadingGif = 'images/page-loader.gif';
	

	function loader(){
	
		echo ('<div style="top:20px; z-index: 9999; position: absolute; width: '.$this->width.'px; left: 50%; top: 50%; margin-left: -'.($this->width/2).'px; margin-top: -'.($this->height/2).'px; height: '.$this->height.'px; background-color: #ffffff; vertical-align: center; text-align:center; padding: 15px 0 15px 0; filter:Alpha(opacity=75); -moz-opacity: 0.75; opacity: 0.75; background-image: url(\''.$this->loadingGif.'\'); background-repeat: no-repeat; background-position: center;" id="loading"></div>') . "\r\n";
        
		echo ('<script language="javascript">
				<!--
				if (window.attachEvent) {

					window.attachEvent("onload", function() { document.getElementById("loading").style.display = "none"; });

				} else if (window.addEventListener) {

					window.addEventListener("load", function() { document.getElementById("loading").style.display = "none"; }, false);

				} else {

					window.onload = document.getElementById("loading").style.display = "none";;

				}
				-->
				</script>') . "\r\n";
		
		ob_end_flush();
		
		flush();

	
	}

}
?>
