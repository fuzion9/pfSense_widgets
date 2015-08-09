<?php
/*
	DHCP_leases.widget.php
	Modified by Dave Field
        (original by Bobby Earl @ www.bobbyearl.com) why reinvent the wheel ?
	Last Modified: 2015-08-09

Changes from Original:
* Added and included widgets/include/DHCP_leases.inc to provide a clickable link
    to DHCP Page
*  Added lease type, and online/offline statuses (online always for active dynamic
    leases, and actual status for dynamic leases
*  Added count for 
*  Added count for static leases
    


Original File/Inspiration:

	status_dhcp_leases.php
	Copyright (C) 2004-2009 Scott Ullrich
	All rights reserved.

	originially part of m0n0wall (http://m0n0.ch/wall)
	Copyright (C) 2003-2004 Manuel Kasper <mk@neon1.net>.
	All rights reserved.

	Redistribution and use in source and binary forms, with or without
	modification, are permitted provided that the following conditions are met:

	1. Redistributions of source code must retain the above copyright notice,
	   this list of conditions and the following disclaimer.

	2. Redistributions in binary form must reproduce the above copyright
	   notice, this list of conditions and the following disclaimer in the
	   documentation and/or other materials provided with the distribution.

	THIS SOFTWARE IS PROVIDED ``AS IS'' AND ANY EXPRESS OR IMPLIED WARRANTIES,
	INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY
	AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
	AUTHOR BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY,
	OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
	SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
	INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
	CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
	ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
	POSSIBILITY OF SUCH DAMAGE.
*/
@require_once("/usr/local/www/widgets/include/DHCP_leases.inc");
@require_once("guiconfig.inc");
$leasesfile = "{$g['dhcpd_chroot_path']}/var/db/dhcpd.leases";

function remove_duplicate($array, $field) {
  foreach ($array as $sub) { $cmp[] = $sub[$field]; }
  $unique = array_unique(array_reverse($cmp,true));
  foreach ($unique as $k => $rien) { $new[] = $array[$k]; }
  return $new;
}

$awk = "/usr/bin/awk";

/* this pattern sticks comments into a single array item */
$cleanpattern = "'{ gsub(\"#.*\", \"\");} { gsub(\";\", \"\"); print;}'";

/* We then split the leases file by } */
$splitpattern = "'BEGIN { RS=\"}\";} {for (i=1; i<=NF; i++) printf \"%s \", \$i; printf \"}\\n\";}'";

/* stuff the leases file in a proper format into a array by line */
exec("/bin/cat {$leasesfile} | {$awk} {$cleanpattern} | {$awk} {$splitpattern}", $leases_content);
$leases_count = count($leases_content);
exec("/usr/sbin/arp -an", $rawdata);
$arpdata = array();
foreach ($rawdata as $line) {
	$elements = explode(' ',$line);
	if ($elements[3] != "(incomplete)") {
		$arpent = array();
		$arpent['ip'] = trim(str_replace(array('(',')'),'',$elements[1]));
		$arpdata[] = $arpent['ip'];
	}
}

$leases = array();
$i = 0;
$l = 0;
$p = 0;

// Put everything together again
while($i < $leases_count) {
	/* split the line by space */
	$data = explode(" ", $leases_content[$i]);
	
	/* walk the fields */
	$f = 0;
	$fcount = count($data);        
	
	/* with less then 20 fields there is nothing useful */
	if($fcount < 20) {
		$i++;
		continue;
	}
	
	while($f < $fcount) {
		switch($data[$f]) {
			case "lease":
				$leases[$l]['ip'] = $data[$f+1];
				$leases[$l]['type'] = "dynamic";
				$f = $f+2;
				break;
			case "tstp":
				$f = $f+3;
				break;
			case "tsfp":
				$f = $f+3;
				break;
			case "atsfp":
				$f = $f+3;
				break;
			case "cltt":
				$f = $f+3;
				break;
			case "binding":
				switch($data[$f+2]) {
					case "active":
						$leases[$l]['act'] = "active";
						break;
					case "free":
						$leases[$l]['act'] = "expired";
						$leases[$l]['online'] = "offline";
						break;
					case "backup":
						$leases[$l]['act'] = "reserved";
						$leases[$l]['online'] = "offline";
						break;
				}
				$f = $f+1;
				break;
			case "next":
				/* skip the next binding statement */
				$f = $f+3;
				break;
			case "rewind":
				/* skip the rewind binding statement */
				$f = $f+3;
				break;
			case "hardware":
				$leases[$l]['mac'] = $data[$f+2];
				/* check if it's online and the lease is active */
				if (in_array($leases[$l]['ip'], $arpdata)) {
					$leases[$l]['online'] = 'online';
				} else {
					$leases[$l]['online'] = 'offline';
				}
				$f = $f+2;
				break;
			case "client-hostname":
				if($data[$f+1] <> "") {
					$leases[$l]['hostname'] = preg_replace('/"/','',$data[$f+1]);
				} else {
					$hostname = gethostbyaddr($leases[$l]['ip']);
					if($hostname <> "") {
						$leases[$l]['hostname'] = $hostname;
					} 
				}
				$f = $f+1;
				break;
			case "uid":
				$f = $f+1;
				break;
		}
                if (!$leases[$l]['hostname']){ $leases[$l]['hostname'] = "--"; }
                $leases[$l]['status'] = "<span style='color:green'>Online</span>";
		$f++;
	}
	$l++;
	$i++;
}

/* remove duplicate items by mac address */
if(count($leases) > 0) {
	$leases = remove_duplicate($leases,"ip");
        $leases_count = count($leases);
}

if(count($pools) > 0) {
	$pools = remove_duplicate($pools,"name");
	asort($pools);
}

foreach($config['interfaces'] as $ifname => $ifarr) {
	if (is_array($config['dhcpd'][$ifname]) && 
		is_array($config['dhcpd'][$ifname]['staticmap'])) {
		foreach($config['dhcpd'][$ifname]['staticmap'] as $static) {
			$slease = array();
			$slease['ip'] = $static['ipaddr'];
			$slease['type'] = "static";
			$slease['mac'] = $static['mac'];
			$slease['start'] = "";
			$slease['end'] = "";
			$slease['hostname'] = "<strong>".htmlentities($static['hostname'])."</strong>";
			$slease['act'] = "static";
			$online = exec("/usr/sbin/arp -an |/usr/bin/grep {$slease['mac']}| /usr/bin/wc -l|/usr/bin/awk '{print $1;}'");
			if ($online == 1) {
				$slease['status'] = "<span style='color:green'>Online</span>";
				$slease['online'] = 'online';
			} else {
				$slease['online'] = 'offline';
                                $slease['status'] = "<span style='color:red'>Offline</span>";
			}
			$leases[] = $slease;
		}
	}
}

?>
<table class="tabcont sortable" width="100%" border="0" cellpadding="0" cellspacing="0">
  <tr>
    <td class="listhdrr"><a href="#"><?=gettext("IP address"); ?></a></td>
    <td class="listhdrr"><a href="#"><?=gettext("Hostname"); ?></a></td>
    <td class="listhdrr"><a href="#"><?=gettext("Lease Type"); ?></a></td>
    <td class="listhdrr"><a href="#"><?=gettext("Status"); ?></a></td>
	</tr>
<?php
$dynamic_leases=0;
foreach ($leases as $data) {
	if (($data['act'] == "active") || ($data['act'] == "static") || ($_GET['all'] == 1)) {
                
		if ($data['act'] != "active" && $data['act'] != "static") {
			$fspans = "<span class=\"gray\">";
			$fspane = "</span>";
		} else {                        
			$fspans = $fspane = "";
		}
		
		$lip = ip2ulong($data['ip']);
		if ($data['act'] == "static") {
			foreach ($config['dhcpd'] as $dhcpif => $dhcpifconf) {
				if(is_array($dhcpifconf['staticmap'])) {
					foreach ($dhcpifconf['staticmap'] as $staticent) {
						if ($data['ip'] == $staticent['ipaddr']) {
							$data['if'] = $dhcpif;
							break;
						}
					}
				}
				/* exit as soon as we have an interface */
				if ($data['if'] != "")
                                        $dynamic_leases++;
					break;
			}
		} else {
            foreach ($config['dhcpd'] as $dhcpif => $dhcpifconf) {	
               	if (($lip >= ip2ulong($dhcpifconf['range']['from'])) && ($lip <= ip2ulong($dhcpifconf['range']['to']))) {
                   	$data['if'] = $dhcpif;
                   	break;
				}
			}
		}		
		echo "<tr>\n";
		echo "<td class=\"listlr\">{$fspans}{$data['ip']}{$fspane}&nbsp;</td>\n";
		echo "<td class=\"listr\">{$fspans}"  . $data['hostname'] . "{$fspane}&nbsp;</td>\n";
                echo "<td class=\"listr\">{$fspans}"  . $data['type'] . "{$fspane}&nbsp;</td>\n";
                echo "<td class=\"listr\">{$fspans}"  . $data['status'] . "{$fspane}&nbsp;</td>\n";
		echo "</tr>\n";
	}
}
?>
</table>
<table class="tabcont" width="100%" border="0" cellpadding="0" cellspacing="0">
    <tr>
        <td class="listhdrr">
            <span style="float:left">Active Dynamic Leases: (<?=$dynamic_leases?>/<?=$leases_count?>)</span>
            <span style="float:right">Static Leases: <?=count($slease)?></span>
        </td>
    </tr>
</table>
<?php if($leases == 0): ?>
<p><strong><?=gettext("No leases file found. Is the DHCP server active"); ?>?</strong></p>
<?php endif; ?>
