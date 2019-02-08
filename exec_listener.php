<?php
#starting worker
pclose(popen('start /B php exec.php 2>nul >nul', "r"));