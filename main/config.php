<script>
    function toggleshow(id) {
        if (document.getElementById(id).style.display == "none")
            document.getElementById(id).style.display = '';
        else
            document.getElementById(id).style.display = "none";

    }
</script>

<?php
//echo $_SESSION['email'];

//session_start();
$con = mysqli_connect("localhost", "root", "", "workflows");

if (mysqli_connect_errno($con)) {
    echo "Failed to connect to MySQL: " . mysqli_connect_error();
}
/*if (isset($_SESSION['email'])) {
    $sql = "select id,status from login where email='" . $_SESSION['email'] . "'";
    $result = mysqli_query($con, $sql);
    $row = mysqli_fetch_array($result);
    $user_id = $row['id'];
    $user_status = $row['status'];
    echo '
        <div style="text-align:right">' . $_SESSION['email'] . '&nbsp; <a href="logout.php">Logout</a> </div>';
} else {
    $user_id = 0;
    $user_status = "";
}*/


/* $result = mysqli_query($con,"show tables");

  while($row = mysqli_fetch_array($result))
  {
  echo $row[0];
  echo "<br>";
  } */

function fallback($wid, $nodeid, $flag, $last) {
//    echo "in fallback: nodeid=".$nodeid." ";
    global $con;
    array_push($flag, $nodeid);
    $result1 = selectfromdb("transition", array("done_innodes,path"), "workflow_id='" . $wid . "' and id='" . $nodeid . "'");
    $row1 = mysqli_fetch_array($result1);
    $doneinnodes = unserialize($row1['done_innodes']);
    $out = unserialize($row1['path']);
    foreach ($out as $outnode) {
        if (!in_array($outnode, $flag))
            if (!in_array($outnode, $last))
                fallback($wid, $outnode, $flag, $last);
    }
    // echo count($doneinnodes);
    //exit(0);
    $gar = array();
    $gar = serialize($gar);
    if (in_array($nodeid, $last)) {

        $update = "update transition set activated=2,start_time='" . time() . "' where " . "workflow_id='" . $wid . "' and id='" . $nodeid . "'";
        if (!mysqli_query($con, $update)) {
            die('Error while inserting in  fallbackfunction' . mysqli_error($con));
        }
    } else {

        $update = "update transition set activated=0,done_innodes='" . $gar . "',start_time='" . time() . "' where " . "workflow_id='" . $wid . "' and id='" . $nodeid . "'";

        if (!mysqli_query($con, $update)) {
            die('Error while inserting in  fallbackfunction' . mysqli_error($con));
        }
        // echo "<br>";print_r($doneinnodes); echo " : doneinnode<br>";
        // print_r($last);echo"<br>";

        foreach ($doneinnodes as $innode) {
            if (!in_array($innode, $flag)) {
                if (!in_array($nodeid, $last)) {
                    fallback($wid, $innode, $flag, $last);
                    //  echo "called";
                    // exit(0);
                }
            }
        }
    }
}

function logg($message) {
    $current = file_get_contents("log");
    $message.=" :: email: " . $_SESSION['email'] . " name:" . $_SESSION['username'] . " At " . date("m/d/y G.i:s", time()) . "\n";
    $current.=$message;
    file_put_contents("log", $current);
}

function deactivate($wid, $nodeid, $changedinputs, $fl, $deactivated) {
//    echo "in fallback: nodeid=".$nodeid." ";
    global $con;
    $result1 = selectfromdb("transition", array("path", "fval", "done_innodes"), "workflow_id='" . $wid . "' and id='" . $nodeid . "'");
    $row1 = mysqli_fetch_array($result1);
    $out = unserialize($row1['path']);
    $fval = unserialize($row1['fval']);
    // print_r($deactivated);echo " deactnodeid=".$nodeid."<br>";
    $done = unserialize($row1['done_innodes']);
    if ($fl != 1) {

        foreach ($fval as $nf) {
            print_r($nf);
            echo "nodeid=" . $nodeid . "<br>";
            if (in_array($nf, $changedinputs))
                $fl = 1;
            if ($fl === 1)
                break;
        }
    }
    // print_r($changedinputs); echo"<br>";
    /*  print_r($fval); echo"<br>";
      print_r($changedinputs); echo"<br>";
      print_r($out);echo " fl=".$fl."<br>";exit(0); */
    if ($fl)
        if (!in_array($nodeid, $deactivated))
            array_push($deactivated, $nodeid);
    // print_r($deactivated);echo " deactnodeid=".$nodeid."<br><br>    ";
    foreach ($out as $outnode) {
        deactivate($wid, $outnode, $changedinputs, $fl, $deactivated);
    }
    // echo count($doneinnodes);
    //exit(0);

    if ($fl == 0)
        return;
    // echo "deactivating node=".$nodeid."<br>";
    $gar = array();
    foreach ($done as $i) {
        if (!in_array($i, $deactivated))
            array_push($gar, $i);
    }
    $gar = serialize($gar);

    $update = "update transition set activated=0,done_innodes='" . $gar . "',start_time='" . time() . "' where " . "workflow_id='" . $wid . "' and id='" . $nodeid . "'";

    if (!mysqli_query($con, $update)) {
        die('Error while updatind in  deactivate function' . mysqli_error($con));
    }
}

function remapworkflow($wid, $map) {

    $result = selectfromdb("transition", array("*"), "workflow_id=" . $wid);
    while ($row = mysqli_fetch_array($result)) {
        $lst = unserialize($row['mustbedeactive']);
        $i = 0;
        foreach ($lst as $id) {
                  if (array_key_exists($id, $map))
                          $lst[$i]=$map[$id]["start"];

            $i+=1;
        }
        $row['mustbedeactive'] = serialize($lst);
        $inlist = unserialize($row['innodes']);
        $lst = array();
        foreach ($inlist as $list) {
            $p = array();
            foreach ($list as $i) {
                if (array_key_exists($i, $map))
                    array_push($p, $map[$i]["end"]);
                else
                    array_push($p, $i);
            }
            array_push($lst, $p);
        }
        $row['innodes'] = serialize($lst);
        $outlist = unserialize($row['outnodes']);
        $lst = array();
        foreach ($outlist as $i) {
            if (array_key_exists($i, $map))
                array_push($lst, $map[$i]["start"]);
            else
                array_push($lst, $i);
        }
        $row['outnodes'] = serialize($lst);
        $br = unserialize($row['branch']);
        $cbr = array();
        foreach ($br as $pair) {
            $out = array();
            foreach ($pair['outnodes'] as $o) {
                if (array_key_exists($o, $map))
                    array_push($out, $map[$o]["start"]);
                else
                    array_push($out, $o);
            }
            $pair['outnodes'] = $out;
            array_push($cbr, $pair);
            unset($out);
        }
        $row['branch'] = serialize($cbr);
        //fallback
        $br = unserialize($row['fallback']);
        $cbr = array();
        foreach ($br as $pair) {
            $out = array();
            foreach ($pair['outnodes'] as $o) {
                if (array_key_exists($o, $map))
                    array_push($out, $map[$o]["end"]);
                else
                    array_push($out, $o);
            }
            $pair['outnodes'] = $out;
            array_push($cbr, $pair);
            unset($out);
        }
        $row['fallback'] = serialize($cbr);
        //functions
        $func = unserialize($row['functions']);
        $bb = $func['before'];
        $cb = array();
        foreach ($bb as $b) {
            $p = array();
            foreach ($b['data'] as $info) {
                if ($info[0] == 'node') {

                    if (array_key_exists($info[1], $map))
                        $info[1] = $map[$info[1]]["end"];
                }
                else if ($info[0] == "table") {
                    $i = 0;
                    foreach ($info[3] as $condition) {
                        $v = explode(",", $condition[1]);
                        if (count($v) == 2) {
                            if (array_key_exists($v[0], $map))
                                $info[3][$i][1] = $map[$v[0]]["end"] . "," . $v[1];
                        }
                        $i+=1;
                    }
                }
                array_push($p, $info);
            }
            $b['data'] = $p;
            $cbr = array();
            foreach ($b['branch'] as $pair) {
                $out = array();
                foreach ($pair['outnodes'] as $o) {
                    if (array_key_exists($o, $map))
                        array_push($out, $map[$o]["start"]);
                    else
                        array_push($out, $o);
                }
                $pair['outnodes'] = $out;
                array_push($cbr, $pair);
                unset($out);
            }
            $b['branch'] = $cbr;
            $cbr = array();
            foreach ($b['fallback'] as $pair) {
                $out = array();
                foreach ($pair['outnodes'] as $o) {
                    if (array_key_exists($o, $map))
                        array_push($out, $map[$o]["end"]);
                    else
                        array_push($out, $o);
                }
                $pair['outnodes'] = $out;
                array_push($cbr, $pair);
                unset($out);
            }
            $b['fallback'] = $cbr;
            array_push($cb, $b);
        }
        $func['before'] = $cb;
        $bb = $func['after'];
        $cb = array();
        foreach ($bb as $b) {
            $p = array();
            foreach ($b['data'] as $info) {
                if ($info[0] == 'node') {

                    if (array_key_exists($info[1], $map))
                        $info[1] = $map[$info[1]]["end"];
                }
                //---------------------------------------------
                else if ($info[0] == "table") {
                    $i = 0;
                    foreach ($info[3] as $condition) {
                        $v = explode(",", $condition[1]);
                        if (count($v) == 2) {
                            if (array_key_exists($v[0], $map))
                                $info[3][$i][1] = $map[$v[0]]["end"] . "," . $v[1];
                        }
                        $i+=1;
                    }
                }
                array_push($p, $info);
            }
            $b['data'] = $p;
            $cbr = array();
            foreach ($b['branch'] as $pair) {
                $out = array();
                foreach ($pair['outnodes'] as $o) {
                    if (array_key_exists($o, $map))
                        array_push($out, $map[$o]["start"]);
                    else
                        array_push($out, $o);
                }
                $pair['outnodes'] = $out;
                array_push($cbr, $pair);
                unset($out);
            }
            $b['branch'] = $cbr;
            $cbr = array();
            foreach ($b['fallback'] as $pair) {
                $out = array();
                foreach ($pair['outnodes'] as $o) {
                    if (array_key_exists($o, $map))
                        array_push($out, $map[$o]["end"]);
                    else
                        array_push($out, $o);
                }
                $pair['outnodes'] = $out;
                array_push($cbr, $pair);
                unset($out);
            }
            $b['fallback'] = $cbr;
            array_push($cb, $b);
        }
        $func['after'] = $cb;
        $row['functions'] = serialize($func);
        //fval
        $fv = unserialize($row['fval']);
        $cfv = array();
        foreach ($fv as $p) {
            if (array_key_exists($p[0], $map))
                $p[0] = $map[$p[0]]["end"];
            array_push($cfv, $p);
        }


        $row['fval'] = serialize($cfv);
        updatedb("transition", $row, "workflow_id=" . $wid . " and id=" . $row['id']);
    }
    //inputs
    $result = selectfromdb("inputs", array("*"), "workflow_id=" . $wid);
    while ($row = mysqli_fetch_array($result)) {

        $v = unserialize($row['val']);
        $cv = array();
        foreach ($v as $lst) {
            if ($lst[0]==1) {//fval
                $fval = "";
               
                $v = explode(";", $lst[1]); //parameters + functionlist
                $paramlist = explode(":", $v[0]);
                foreach ($paramlist as $param) {
                    $l = explode(",", $param);
                    if (count($l) == 1) {
                        $fval.=$l[0] . ":";
                    } else if (count($l) == 2) {//fetch data from previous node
                        if (array_key_exists($l[0], $map))
                            $l[0] = $map[$l[0]]["end"];
                        $fval.= $l[0] . "," . $l[1] . ":";
                    }
                }
                $fval[strlen($fval) - 1] = ";";
                $fval.= $v[1];

                echo "node=" . $row['transition_id'] . " inputname=" . $row['name'] . " fval=" . $fval . "<br>";
                $lst[1] = $fval;
            }
            //------------------------------------------------------------
            else if ($lst[0] == 2) {//from database
                $key = $lst[1];
                $conditions = $key[2];
                $condition = "";
                $ct = count($conditions);
                $i = 0;
                foreach ($conditions as $cond) {
                    $v = explode(",", $cond[1]);
                    if (count($v) == 2) {
                        if (array_key_exists($v[0], $map))
                            $lst[1][2][$i][1] = $map[$v[0]]["end"] . "," . $v[1];
                      //  echo $lst[1][2][$i][1]."<br>";
                    }
                    $i+=1;
                }
            }
            //exit(0);    
            if ($lst[2][0]) {//get
                $fval = "";
                $v = explode(";", $lst[2][1]); //parameters + functionlist
                $paramlist = explode(":", $v[0]);
                foreach ($paramlist as $param) {
                    $l = explode(",", $param);
                    if (count($l) == 1) {
                        $fval.=$l[0] . ":";
                    } else if (count($l) == 2) {//fetch data from previous node
                        if (array_key_exists($l[0], $map))
                            $l[0] = $map[$l[0]]["end"];
                        $fval.= $l[0] . "," . $l[1] . ":";
                    }
                }
                $fval[strlen($fval) - 1] = ";";

                $fval.= $v[1];
                $lst[2][1] = $fval;
            }
            array_push($cv, $lst);
        }
        $row['val'] = serialize($cv);
        updatedb("inputs", $row, "workflow_id=" . $wid . " and transition_id=" . $row['transition_id'] . " and id=" . $row['id']);
    }
}

function remapnode($row, $prepend) {

    $row['id'] = $prepend . $row['id'];
    $lst = unserialize($row['mustbedeactive']);
    $i = 0;
    foreach ($lst as $id) {
        $lst[$i] = $prepend . $id;
        $i+=1;
    }
    $row['mustbedeactive'] = serialize($lst);
    // echo "nodeid".$row['id']."<br>";
    //innodes
    $inlist = unserialize($row['innodes']);
    // print_r($inlist);echo "  :inlist<br>";
    $lst = array();
    foreach ($inlist as $list) {
        $p = array();
        foreach ($list as $i) {
            array_push($p, $prepend . $i);
        }
        array_push($lst, $p);
    }
    // print_r($lst);echo "  :inlist<br>";
    $row['innodes'] = serialize($lst);
    //outnodes

    $outlist = unserialize($row['outnodes']);
    //  print_r($outlist);echo "  :outlist<br>";
    $lst = array();
    foreach ($outlist as $i) {
        array_push($lst, $prepend . $i);
    }
    // print_r($lst);echo "  :outlist<br>";
    $row['outnodes'] = serialize($lst);
    //branch

    $br = unserialize($row['branch']);
    $cbr = array();
    foreach ($br as $pair) {
        $out = array();
        foreach ($pair['outnodes'] as $o) {

            array_push($out, $prepend . $o);
        }
        $pair['outnodes'] = $out;
        array_push($cbr, $pair);
        unset($out);
    }
    $row['branch'] = serialize($cbr);
    //fallback
    $br = unserialize($row['fallback']);
    $cbr = array();
    foreach ($br as $pair) {
        $out = array();
        foreach ($pair['outnodes'] as $o) {
            array_push($out, $prepend . $o);
        }
        $pair['outnodes'] = $out;
        array_push($cbr, $pair);
        unset($out);
    }
    // print_r($br);echo "  :branch<br>";
    $func = unserialize($row['functions']);
    $bb = $func['before'];
    $cb = array();
    foreach ($bb as $b) {
        $p = array();
        foreach ($b['data'] as $info) {
            if ($info[0] == 'node') {

                $info[1] = $prepend . $info[1];
            }
            //--------------------------------------------------
            else if ($info[0] == "table") {
                $i = 0;
                foreach ($info[3] as $condition) {
                    $v = explode(",", $condition[1]);
                    if (count($v) == 2) {
                        $info[3][$i][1] = prepend . $info[3][$i][1];
                    }
                    $i+=1;
                }
            }
            array_push($p, $info);
        }
        $b['data'] = $p;
        $cbr = array();
        foreach ($b['branch'] as $pair) {
            $out = array();
            foreach ($pair['outnodes'] as $o) {
                array_push($out, $prepend . $o);
            }
            $pair['outnodes'] = $out;
            array_push($cbr, $pair);
            unset($out);
        }
        $b['branch'] = $cbr;
        $cbr = array();
        foreach ($b['fallback'] as $pair) {
            $out = array();
            foreach ($pair['outnodes'] as $o) {
                array_push($out, $prepend . $o);
            }
            $pair['outnodes'] = $out;
            array_push($cbr, $pair);
            unset($out);
        }
        $b['fallback'] = $cbr;
        array_push($cb, $b);
    }
    $func['before'] = $cb;
    $bb = $func['after'];
    $cb = array();
    foreach ($bb as $b) {
        $p = array();
        foreach ($b['data'] as $info) {
            if ($info[0] == 'node') {

                $info[1] = $prepend . $info[1];
            }//------------------------------------------------
            else if ($info[0] == "table") {
                $i = 0;
                foreach ($info[3] as $condition) {
                    $v = explode(",", $condition[1]);
                    if (count($v) == 2) {
                        $info[3][$i][1] = prepend . $info[3][$i][1];
                    }
                    $i+=1;
                }
            }
            array_push($p, $info);
        }
        $b['data'] = $p;
        $cbr = array();
        foreach ($b['branch'] as $pair) {
            $out = array();
            foreach ($pair['outnodes'] as $o) {
                array_push($out, $prepend . $o);
            }
            $pair['outnodes'] = $out;
            array_push($cbr, $pair);
            unset($out);
        }
        $b['branch'] = $cbr;
        $cbr = array();
        foreach ($b['fallback'] as $pair) {
            $out = array();
            foreach ($pair['outnodes'] as $o) {
                array_push($out, $prepend . $o);
            }
            $pair['outnodes'] = $out;
            array_push($cbr, $pair);
            unset($out);
        }
        $b['fallback'] = $cbr;
        array_push($cb, $b);
    }
    $func['after'] = $cb;
    // print_r($func);echo "  :func<br>";
    $row['functions'] = serialize($func);
    //fval

    $fv = unserialize($row['fval']);
    // print_r($fv);echo "  :fvalc<br>";
    $cfv = array();
    foreach ($fv as $p) {
        $p[0] = $prepend . $p[0];
        array_push($cfv, $p);
    }

//print_r($cfv);echo "  :fvalc<br>";
    $row['fval'] = serialize($cfv);

    return $row;
}

function remapinput($row, $prepend) {
    $row['transition_id'] = $prepend . $row['transition_id'];
    $v = unserialize($row['val']);
    $cv = array();
    foreach ($v as $lst) {
        if ($lst[0] == 1) {//fval
            $fval = "";
            // echo $lst[1]."<br>";
            $v = explode(";", $lst[1]); //parameters + functionlist
            $paramlist = explode(":", $v[0]);
            // print_r($paramlist);echo "<br>";
            foreach ($paramlist as $param) {
                $l = explode(",", $param);
                if (count($l) == 1) {
                    $fval.=$l[0] . ":";
                } else if (count($l) == 2) {//fetch data from previous node
                    $fval.=$prepend . $l[0] . "," . $l[1] . ':';
                }
            }

            $fval[strlen($fval) - 1] = ";";
            $fval.= $v[1];
            //echo $fval." sdfsfs";
            //exit(0);
            $lst[1] = $fval;
        } //--------------------------------------------------------
        else if ($lst[0] == 2) {//from database
            $key = $lst[1];
            $conditions = $key[2];
            $condition = "";
            $ct = count($conditions);
            $i = 0;
            foreach ($conditions as $cond) {
                $v = explode(",", $cond[1]);
                if (count($v) == 2)
                    $lst[1][2][$i][1] = $prepend . $lst[1][2][$i][1];
                $i+=1;
            }
        }
        if ($lst[2][0]) {//get
            $fval = "";
            $v = explode(";", $lst[2][1]); //parameters + functionlist
            $paramlist = explode(":", $v[0]);
            foreach ($paramlist as $param) {
                $l = explode(",", $param);
                if (count($l) == 1) {
                    $fval.=$l[0] . ":";
                } else if (count($l) == 2) {//fetch data from previous node
                    $fval.=$prepend . $l[0] . "," . $l[1] . ":";
                }
            }
            $fval[strlen($fval) - 1] = ";";

            $fval.= $v[1];
            $lst[2][1] = $fval;
        }
        array_push($cv, $lst);
    }
    $row['val'] = serialize($cv);
    return $row;
}

function substitute($record, $fwid, $name, $allinput = array()) {
    $wid = $record[0];
    $sid = "";
    $prepend = $wid . "00";
    $p = array();
    $result = selectfromdb("function_transition", array("*"), "workflow_id=" . $fwid);
    while ($row = mysqli_fetch_array($result)) {
        //print_r($row); echo "-----row <br>";
        /*  $c=0;
          $val=array();
          foreach ($row as $key => $value) {
          $c+=1;
          array_push($val,$row[$key]);

          }

          echo "<br>col count=".$c."<br>";
          foreach($val as $v)echo $v."::<br>";exit(0); */
        $row = remapnode($row, $prepend); //change node ids
        //print_r($row); echo ":::::row <br>";

        if ($row['type'] == "start") {
            if($record[2]!="start")$row['activated']="0";
            $sid = $row['id'];
            foreach ($allinput as $in) {
                $in[1] = $sid; //modify  node id;
               // echo 
               //logg($in[2]." ".$in[3]);
              //  echo count($in);exit(0);
                insertintodb("inputs", $in, 0);
              // exit(0);
            }
            $row['start_time'] = $record[11];
            $p['start'] = $row['id'];
            $row['innodes'] = $record[3];
            if ($record[2] == "start")
                $row['type'] = "start";
        } else if ($row['type'] == "end") {
            $row['outnodes'] = $record[5];
            $p['end'] = $row['id'];
        }
        if ($row['type'] != "start" || $record[2] != "start")
            $row['type'] = "intermediatefunction:" . $name;

        $row['workflow_id'] = $wid;
        $val = array();
        $c = 0;

        foreach ($row as $key => $value) {

            if ($c % 2)
                array_push($val, $row[$key]);
            $c+=1;
        }
        // echo "<br>col count=".$c."<br>";
        //foreach($val as $v)echo $v."::<br>";
        insertintodb("transition", $val, 1); //exit(0);
    }
    $result = selectfromdb("function_inputs", array("*"), "workflow_id=" . $fwid);
    while ($row = mysqli_fetch_array($result)) {
        $row = remapinput($row, $prepend);
        $row['workflow_id'] = $wid;
        $val = array();
        $c = 0;

        foreach ($row as $key => $value) {

            if ($c % 2 && $c != 1)
                array_push($val, $row[$key]);
            $c+=1;
        }
        insertintodb("inputs", $val, 0);
    }
    return $p;
}

function fallforward($wid, $nodeid, $fl) {
//    echo "in fallback: nodeid=".$nodeid." ";
    global $con;
    $result1 = selectfromdb("transition", array("path"), "workflow_id='" . $wid . "' and id='" . $nodeid . "'");
    $row1 = mysqli_fetch_array($result1);
    $out = unserialize($row1['path']);

    foreach ($out as $outnode) {
        fallforward($wid, $outnode, $fl + 1);
    }
    // echo count($doneinnodes);
    //exit(0);
    if ($fl == 0)
        return;
    $gar = array();
    $gar = serialize($gar);

    $update = "update transition set activated=0,done_innodes='" . $gar . "',start_time='" . time() . "' where " . "workflow_id='" . $wid . "' and id='" . $nodeid . "'";

    if (!mysqli_query($con, $update)) {
        die('Error while updatind in  deactivate function' . mysqli_error($con));
    }
}

function getstatus($wid, $nid, $space = "-") {
    $result = selectfromdb("transition", array("done_innodes", "path", "activated"), "workflow_id=" . $wid . " and id=" . $nid);
    $row1 = mysqli_fetch_array($result);
    echo "<h4>|" . $space . " Taskid=" . $nid . "  Activated=" . $row1['activated'] . "</h4>";
    //exit(0);
    $result2 = selectfromdb("inputs", array("name", "chosen_val"), "workflow_id=" . $wid . " and transition_id=" . $nid);

    $space.="----";
    //echo ":".$space.":<br>";
    while ($row = mysqli_fetch_array($result2)) {
        echo "|" . $space . " " . $row['name'] . ':"' . $row['chosen_val'] . '"<br>';
    }
    $space.="-------";
    $path = unserialize($row1['path']);
    foreach ($path as $i) {
        getstatus($wid, $i, $space);
    }
}

function callfunc($funcname, $parameters) {//array of parameters
    if($funcname=="double")
    {
        
        $a= $parameters*2;
        //echo "in double= ".$a;
//        /exit(0);
        return $a;
    }
    
    if ($funcname == "reverse") {//reverse func
        return "This is the otput of reverse function :)";
    } else if ($funcname == "display") {//reverse func
        $s = "<br>in function " . $funcname . "  ist parameter:" . $parameters[0] . "<br>";
        echo $s;
        // print_r($parameters);echo "-----<br>";
    } else if ($funcname == "display2") {//reverse func
        $s ="";
        foreach($parameters as $a)$s.=$a." ";
        echo $s."<br>";
        //  print_r($parameters);echo "-----<br>";
    } else if ($funcname == 'falsefunc')
        return 0;
    else if ($funcname == 'truefunc')
        return "true";
    else if ($funcname == 'func1')
        return "checkfunc1";
    else if ($funcname == 'none')
        return $parameters[0];
    else if ($funcname == 'increment')
        return $parameters[0]+1;
    else if($funcname==='calculate'){
        //return 900;
       // print_r($parameters);
        $a=$parameters[0]+($parameters[1])*$parameters[2];
       // echo $a;
        return $a;
       // exit(0);
    }
    else if($funcname=='none'){
        if (gettype($parameters) === "array")
            return $parameters[0] ;
        else
            return $parameters ;
    }
    else {
        /*if (gettype($parameters) === "array")
            return $parameters[0] ;
        else
            return $parameters ;*/
        return 1;
         
      
    }
    return 1;
}

function isvalid($originalval, $property, $val, $operator) {
    print_r($originalval);
    echo "<br>";
    print_r($property);
    echo "<br>";
    print_r($val);
    echo "<br>";
    print_r($operator);
    echo "<br>--";
    if ($property === "value")
        $val = $originalval;
    return true;
}

function updatedb($table, $valarray, $where = "id>0") {
    global $con;
    $schema = array();

    if ($result = mysqli_query($con, "describe $table")) {
        while ($row = mysqli_fetch_array($result)) {
            array_push($schema, $row[0]);
        }
    } else {
        die('Error while updating in ' . $table . ': ' . mysqli_error($con));
    }
    $update = "update " . $table . " set ";

    foreach ($schema as $col)
        $update.=$col . "='" . $valarray[$col] . "',";
    $update[strlen($update) - 1] = ' ';
    $update.="where " . $where;
    // echo $update;
    if (!mysqli_query($con, $update)) {
        die('Error while updating  ' . $table . ': ' . mysqli_error($con));
    }
}

function insertintodb($table, $valuearray, $fl, $columns = array()) {//receives array of values
    global $con;
    $schema = "(";
    $i = 0;
    
    if (!count($columns)) {
        if ($result = mysqli_query($con, "describe $table")) {
            while ($row = mysqli_fetch_array($result)) {

                if ($fl && !$i)
                    $schema.=$row[0] . ",";
                else if ($i)
                    $schema.=$row[0] . ",";
                $i+=1;
                //echo "<br>";
            }
            $schema[strlen($schema) - 1] = ')';
        }
    }
    else {
        foreach ($columns as $column) {
            $schema.=$column . ",";
        }
        $schema[strlen($schema) - 1] = ')';
    }
    if ($i) {
       
        $arrlength = count($valuearray);
        $values = "(";
        for ($x = 0; $x < $arrlength; $x++) {
            $values.="'" . $valuearray[$x] . "',";
        }
        $values[strlen($values) - 1] = ')';
        // echo 'table='.$table."<br/>";
        // echo $values." : <br/>";
        //return;
        $query = "insert into $table " . $schema . " values" . $values;
   //         echo $query."<br/>";
// echo "here:".$arrlength."<br><br><br><br>";//exit(0);

        if (!mysqli_query($con, $query)) {
            die('Error while inserting in ' . $table . ': ' . mysqli_error($con));
        }
    }
}

function selectfromdb($table, $columns = array('id'), $where = "id>0") {
    global $con;
    $select = "select ";
    foreach ($columns as $col) {
        $select.=$col . ",";
    }
    $select[strlen($select) - 1] = ' ';
    $select.="from $table where $where";
    //echo $select.'<br>'; 

    if (!$result = mysqli_query($con, $select)) {
        die('Error in selectfromdb function' . mysqli_error($con));
    }
    return $result;
}

function getmaxidfromdb($table, $columns = " id ", $where = "id>0") {
    global $con;
    if ($result = mysqli_query($con, "select MAX(id) from $table")) {
        $row = mysqli_fetch_array($result);
        return (string) $row[0];
    }
}

function deletefromdb($table, $where) {
    global $con;
}

function myprint($result) {
    while ($row = mysqli_fetch_array($result)) {
        foreach ($row as $col) {
            echo $col . " ";
        }
        echo "<br/>";
    }
}

function extractform($wfid, $taskid, $editable = 0, $retrieve = 0, $function = 0) {//editable1: deactivated $function1:is a function form extartion
    global $con;
    $f = "";
    if ($function)
        $f = "function_";
    echo "<h3>" . $f . "Workflow number: $wfid and Task number: $taskid</h3><br/>";
    $query = "select type,name,val,editable,chosen_val from " . $f . "inputs where workflow_id=$wfid and transition_id=$taskid";
    $result = mysqli_query($con, $query);
    $divid = 'w' . $wfid . 't' . $taskid . 'div';
    echo "<div id='$divid' style=''>";
    while ($row = mysqli_fetch_array($result)) {
        $res = "";
        $arr = unserialize($row['val']);
        // print_r($arr); echo"here";exit(0);
        foreach ($arr as $vals) {
            //print_r($vals); echo '<br>';
            //array( [1,val,array(1,value)], [] , [] ... )

            $key = $vals[1];
            $value = $vals[2][1];
             // echo"</div>::  <br>"; print_r($key);
            //print_r($value); exit(0);
            // print_r($v);
            if ($vals[0] == 1) {//key should come from prevoius node;
                //echo $key."<br>";exit(0);
                $v = explode(";", $key); //parameters + functionlist
                $functionlist = explode(":", $v[1]);
                $paramlist = explode(":", $v[0]);
                $parameters = array();
                foreach ($paramlist as $param) {
                    $l = explode(",", $param);
                    if (count($l) == 1) {
                        array_push($parameters, $param);
                    } else if (count($l) == 2) {//fetch data from previous node
                        $result2 = selectfromdb($f . "inputs", array("chosen_val"), "name='" . $l[1] . "' and transition_id='" . $l[0] . "' and workflow_id='" . $wfid . "'");

                        $row2 = mysqli_fetch_array($result2);
                        if ($row2 === NULL) {

                            echo "</div><h3>Fatal Error in extractform function: the required input:" . "name='" . $l[1] . "' and transition_id='" . $l[0] . "' and workflow_id='" . $wfid . "'" . " was not found</h3><br>";
                            exit(0);
                        }
                        array_push($parameters, $row2['chosen_val']);
                    }
                }

                //  print_r($v); echo '<br>';
                foreach ($functionlist as $function) {
                    $parameters = callfunc($function, $parameters);
                    // print_r($parameters); echo "<br>";
                }
                $key = $parameters;
                //  echo $key; exit(0);
            } else if ($vals[0] == 2) {//key from database
              //  print_r($key);
               // continue;
                
                $table = $key[0];
                $field = $key[1];
             //   echo "</div>table=".$table;exit(0);    
                $conditions = $key[2];
                $condition = "";
                $ct = count($conditions);
               // echo "ct=".$ct."<br>";
                $i = 0;
                foreach ($conditions as $cond) {
                    $i+=1;
                    $condition.=$cond[0];
                    $v = explode(",", $cond[1]);
                  //  print_r($v);echo "<br>".$cond[1]."<br>";
                    if (count($v) == 1) {//data
                        //$condition.="'" . $cond[1] . "' " . ($i == $ct) ? "" : "and ";
                        $condition.="'" . $cond[1] . "' ";
                    if($i!=$ct){$condition.="and ";}
                    } else {//from node
                        $result2 = selectfromdb("inputs", array("chosen_val"), "name='" . $v[1] . "' and transition_id='" . $v[0] . "' and workflow_id='" . $_POST['wid'] . "'");
                        $row2 = mysqli_fetch_array($result2);
                        $condition.="'" . $row['chosen_val'] . "' " ;
                          
                        if($i!=$ct){$condition.="and ";}
                    }
                  //  echo "cndition:".$condition."<br>";
                }
                
                $key=mysqli_fetch_array(selectfromdb($table, array($field), $condition))[$field];
            }
            if ($vals[2][0]) {//value should come from previous nodes
                $v = explode(";", $value); //parameters + functionlist
                $functionlist = explode(":", $v[1]);
                $paramlist = explode(":", $v[0]);
                $parameters = array();
                foreach ($paramlist as $param) {
                    $l = explode(",", $param);
                    if (count($l) == 1) {
                        array_push($parameters, $param);
                    } else if (count($l) == 2) {//fetch data from previous node
                        $result2 = selectfromdb($f . "inputs", array("chosen_val"), "name='" . $l[1] . "' and transition_id='" . $l[0] . "' and workflow_id='" . $wfid . "'");
                        $row2 = mysqli_fetch_array($result2);
                        array_push($parameters, $row2['chosen_val']);
                    }
                }
                //  print_r($v); echo '<br>';
                foreach ($functionlist as $function) {
                    $parameters = callfunc($function, $parameters);
                }
                $value = $parameters;
                // print_r($value); exit(0);
            }
            $kkey = $key;
            // if($retrieve)$kkey=$row['chosen']
               if($row["type"]=='phidden'){if($row['chosen_val']!="")$key=$row['chosen_val'];$row["type"]="hidden";}
               else if($row["type"]=="persistent"){if($row['chosen_val']!="")$key=$row['chosen_val'];$row["type"]=="text";}
            if ($editable && $row['editable'] != "true")
                $res.="<input type='" . $row['type'] . "' name='" . $row['name'] . "' value='" . $key . "' disabled>" . $value . "<br>";
            else
                $res.="<input type='" . $row['type'] . "' name='" . $row['name'] . "' value='" . $key . "'>" . $value . "<br>";
            //if($retrieve)
        }
        $res.="";
        echo "<br/>";
        // echo "HTML code is <br/>";
        // echo '<pre>' . htmlspecialchars($res) . '</pre>';
        if($row["type"]!="hidden")echo $row['name'] . " : " . $res;
        else echo $res;
    }

    echo "</div>";
    $p = "'";
    $p.=$divid . "'";
    echo '<button onclick="toggleshow(' . $p . ');return false;">Details.. </button>';
}

function checkcondition($value, $property, $testvalue, $operator,$wid=0,$nodeid=0) {
   
    if($property=="ifchanged"){
       
        $r= selectfromdb("inputs",array("chosen_val"),"workflow_id=".$wid." and transition_id=".$nodeid." and name='".$testvalue."'");
        $row=  mysqli_fetch_array($r);
        if($row==""){
           
            $ans=1;
        }
        else {
           // echo"here: ".$row['chosen_val']." val=".$value; exit(0);
            
            if($row['chosen_val']!=$value)$ans=1;
            if($row['chosen_val']=="")$ans=0;
            return $ans;
        }
    }
    if ($property == "length") {
        $x = strlen((string) $value);
        if ($operator == "eq") {
            if ($x == $testvalue) {
                $ans = "1";
            } else {
                $ans = "The length of the field should be \eq/ to " . $testvalue;
            }
        } else if ($operator == "gteq") {
            if ($x >= $testvalue) {
                $ans = "1";
            } else {
                $ans = "The length of the field should be \gteq/ than " . $testvalue;
            }
        } else if ($operator == "lteq") {
            if ($x <= $testvalue) {
                $ans = "1";
            } else {
                $ans = "The length of the field should be \lteq/ than " . $testvalue;
            }
        } else if ($operator == "gt") {
            if ($x > $testvalue) {
                $ans = "1";
            } else {
                $ans = "The length of the field should be \gt/ than " . $testvalue;
            }
        } else if ($operator == "lt") {
            if ($x < $testvalue) {
                $ans = "1";
            } else {
                $ans = "The length of the field should be \lt/ than " . $testvalue;
            }
        }
    } else if ($property == "value") {
        $x = $value;
        if ($operator == "eq") {
            if ($x == $testvalue) {
                $ans = "1";
            } else {
                $ans = "The value of the field should be \eq/ to " . $testvalue;
            }
        } else if ($operator == "gteq") {
            if ($x >= $testvalue) {
                $ans = "1";
            } else {
                $ans = "The value of the field should be \gteq/ than " . $testvalue;
            }
        } else if ($operator == "lteq") {
            if ($x <= $testvalue) {
                $ans = "1";
            } else {
                $ans = "The value of the field should be \lteq/ than " . $testvalue;
            }
        } else if ($operator == "gt") {
            if ($x > $testvalue) {
                $ans = "1";
            } else {
                $ans = "The value of the field should be \gt/ than " . $testvalue;
            }
        } else if ($operator == "lt") {
            if ($x < $testvalue) {
                $ans = "1";
            } else {
                $ans = "The value of the field should be \lt/ than " . $testvalue;
            }
        }
    } else if ($property == "alphanumeric") {
        if (ctype_alnum((string) $value)) {
            $ans = "1";
        } else {
            $ans = "1";
            //$ans="The field can only contain alphabets and digits.";
        }
    } else if ($property == "alpha") {
        if (ctype_alpha((string) $value)) {
            $ans = "1";
        } else {
            $ans = "The field can only contain alphabets.";
        }
    } else if ($property == "numeric") {
        if (ctype_digit((string) $value)) {
            $ans = "1";
        } else {
            $ans = "The field can contain only digits.";
        }
    } else if ($property == "email") {
        $email = (string) $value;
        if (eregi("^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,3})$", $email)) {
            $ans = "1";
        } else {
            $ans = "Not a valid email address.";
        }
    } else if ($property == "isselected") {
        if ($value == $testvalue) {
            $ans = "1";
        } else {
            $ans = "0";
        }
    }

    return $ans;
}

//$result=selectfromdb("organization",array("id",'org_name'),"org_name!='root'"); 
//myprint($result);
?>