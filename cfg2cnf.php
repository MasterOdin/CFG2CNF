<?php

error_reporting(E_ERROR | E_PARSE);

function removeEmpty($var)
{
     if(strlen($var) > 0)
        return true;
}
    
class convertor {

    var $convert = array();
    var $convertUnset = array();
    var $hasEmpty = array();
    var $upperCount = 0; // Ui, for i (unused)
    var $lowerCount = 0; // ui, for i (unused)
    var $alphas = array();
    var $alphasReplaced = array();
    var $alphasConverted = array();
    var $alphaCount = 0;
    var $startState = "";
    
    var $addInSlash = false;

    /* 
        returnAllStrings
        @param haystack - string to get all occurences of
        @param needle - needle to work with
        
        Returns all strings recursively replacing needle in string with empty
    */
    function returnAllStrings($haystack,$needle,$key,$currentEmpty)
    {
        $returnArray = array();
        $returns = array();
        preg_match_all("/".$needle."/",$haystack,$matches,PREG_OFFSET_CAPTURE);
        $replaceAll = false;

        foreach($matches[0] as $k4 => $v4)
        {
            if(strlen($haystack) > 1)
            {
                $replace = substr($haystack,$v4[1]);
                $replace = preg_replace("/".$needle."/","",$replace,1);
                $output = substr($haystack,0,$v4[1]).$replace;
                $returns = $this->returnAllStrings($output,$needle,$key,$currentEmpty);
                if($output != $key)
                {
                    $returnArray[] = $output;
                }
            }
            else
            {
                /* we're moving the / up to a different unit production basically */
                if($currentEmpty == $haystack && $key != $currentEmpty)
                {
                    if($key != "S0")
                    {
                        $this->hasEmpty[] = $key;
                    }
                    else
                    {
                        $this->addInSlash = true;
                    }
                }                   
            }
        }
        
        return array_merge($returnArray,$returns);
    }
    
    function convert()
    {
        $this->alphas = array('A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P','Q','R','T','U','V','W','X','Y','Z');
        
        // read in input, setting the new rules, one per line (if valid), if there is a /, note the rule, and then get rid of the /
        $input = array();
        $input = explode("\n",$_POST['grammar']);
        foreach($input as $k => $v)
        {
            $v = trim($v);
            $v = str_replace(" ","",$v);
            $input[$k] = $v;
            $pos = strpos($v,"->");
            if($pos === false || $pos != 1 || ctype_upper(substr($v,0,1)) == false)
            {
                unset($input[$k]);
            }
            else
            {
                $tmp = explode("->",$v);
                if(strpos($v,"/") !== false)
                {
                    $this->hasEmpty[] = substr($v,0,1);
                    $tmp[1] = str_replace("/","",$tmp[1]);
                }
                $this->convert[$tmp[0]] = explode("|",$tmp[1]);

            }
        }
        
        // Step 1 - Create S0 and point it to the S or first rule
        if(array_key_exists("S",$this->convert))
        {
            $this->startState = "S";
        }
        else
        {
            $tmp = array_keys($this->convert);
            $this->startState = $tmp[0];
        }

        $reorder = array();
        $reorder["S0"][] = $this->startState;
        $this->convert = array_merge($reorder,$this->convert);
        unset($reorder);
        foreach($this->convert as $kk => $vv)
            $this->convert[$kk] = array_filter($this->convert[$kk],"removeEmpty");
        
        $this->hasEmpty = array_reverse(array_unique($this->hasEmpty));;
        
        //Step 2 - Removing / productions

        while(count($this->hasEmpty) > 0)
        {
            foreach($this->hasEmpty as $k => $v)
            {
                foreach($this->convert as $kk => $vv)
                {
                    foreach($this->convert[$kk] as $kkk => $vvv)
                    {

                        if(strpos($vvv,"/") !== false && $kk != "S0")
                        {
                            unset($this->convert[$kk][$kkk]);               
                        }
                        
                        if(strpos($vvv,$v) !== false)
                        {
                            // have empty string, now need to come up with all possible strings
                            $this->convert[$kk] = array_merge($this->convert[$kk],$this->returnAllStrings($vvv,$v,$kk,$v));
                            
                            $a = preg_replace("/".$v."/","",$vvv);

                            $this->convert[$kk][] = $a; // make 
                            
                        }
                    }
                    $this->convert[$kk] = array_filter($this->convert[$kk],"removeEmpty");
                    $this->convert[$kk] = array_unique($this->convert[$kk]);
                }
                unset($this->hasEmpty[$k]);
            }
            $this->hasEmpty = array_unique($this->hasEmpty);
        }
        
        // Step 3 - Removing Unit Productions
        $this->convert = array_reverse($this->convert);
        
        foreach($this->convert as $k => $v)
        {
            foreach($v as $kk => $vv)
            {
                if(strlen($vv) == 1 && ctype_upper($vv) == true)
                {
                    $this->convertUnset[$k][] = $kk;
                    $this->convert[$k] = array_merge($this->convert[$k],$this->convert[$vv]);
                }
            }
        }
        
        // iterate through convert array until there's no single non-terminals on the RHS of ->
        $finished = false;
        while(!$finished)
        {
            $finished = true;

            foreach($this->convert as $k => $v)
            {
                foreach($v as $kk => $vv)
                {
                    if(strlen($vv) == 1 && ctype_upper($vv) == true)
                    {
                        $finished = false;
                        $this->convertUnset[$k][] = $kk;
                    }
                }
            }       
            foreach($this->convertUnset as $k => $v)
            {
                foreach($v as $kk => $vv)
                {
                    unset($this->convert[$k][$vv]);
                }
            }
        }
        $this->convert = array_reverse($this->convert);
        
        // Always checking for errors in theh convert array
        foreach($this->convert as $kk => $vv)
        {
            $this->convert[$kk] = array_filter($this->convert[$kk],"removeEmpty");
            $this->convert[$kk] = array_unique($this->convert[$kk]);
        }
        
        
        // Pre Step 4 - Get what's the next letter we can use for replacements
        $gotNextLetter = false;
        
        while(!$gotNextLetter)
        {
            if(array_key_exists($this->alphas[$this->alphaCount],$this->convert))
            {
                $this->alphaCount++;
            }
            else
            {
                $gotNextLetter = true;
            }
        }

        
        // Step 4 - Simply rules to have productions be <= 3
        //          Additionally, if we have aB, replace a with new symbol as necessary
        $finished = false;
        $breakoff = "";
        $num = array("1","2","3","4","5","6","7","8","9");
        $useCount = 0;
        while(!$finished)
        {
            $finished = true;
            foreach($this->convert as $k => $v)
            {
                foreach($v as $kk => $vv)
                {
                    if(strlen($vv) > 2)
                    {
                        $finished = false;
                        $breakOff = substr($vv,(strlen($vv)-2),2);
                        
                        $inner = substr($breakOff,0,1);
                        if($inner == "S")
                        {
                            $inner = substr($breakOff,1,1);
                        }
                        $done = false;
                        $count = 1;
                        $aa = "";
                        while(!$done)
                        {
                            $create = $this->alphas[$this->alphaCount];
                            if(array_key_exists($breakOff,$this->alphasReplaced))
                            {
                                $this->convert[$k][$kk] = str_replace($breakOff,$this->alphasReplaced[$breakOff],$vv);
                                $done = true;
                            }
                            else
                            {
                                $aa = str_replace($breakOff,$create,$vv);
                                $this->convert[$k][$kk] = str_replace($breakOff,$create,$vv);
                                $this->convert[$create][] = $breakOff;
                                $this->alphasReplaced[$breakOff] = $create;
                                $this->alphaCount++;
                                $done = true;
                            }
                        }
                    }
                    elseif(!ctype_upper($vv) && strlen($vv) == 2)
                    {
                        $finished = false;
                        $count = 0;
                        
                        while(!ctype_upper($vv))
                        {
                            $replace = substr($vv,$count,1);
                            if(!ctype_upper($replace))
                            {
                                if(array_key_exists($replace,$this->alphasConverted))
                                {
                                    $useCount = $this->alphasConverted[$replace];
                                }
                                else
                                {
                                    $useCount = $this->alphaCount++;
                                    $this->alphasConverted[$replace] = $useCount;
                                    $this->convert[$this->alphas[$useCount]][] = $replace;
                                }
                                $vv = str_replace($replace,$this->alphas[$useCount],$vv);
                                $this->alphaCount++;
                            }
                            else
                            {
                                $count++;
                            }
                        }
                        $this->convert[$k][$kk] = $vv;
                    }
                }
            }
        }

        /* print final output now */
        print "Original:<br/ >";
        foreach($input as $v)
        {
            print $v."<br />";
        }
        
        print "<br />New:<br />";

        // gotta make sure no duplicates ever!
        foreach($this->convert as $kk => $vv)
        {
            $this->convert[$kk] = array_filter($this->convert[$kk],"removeEmpty");
            $this->convert[$kk] = array_unique($this->convert[$kk]);
        }
        
        // We have an empty string possibility, so S0 get the /
        if($this->addInSlash == true)
        {
            $this->convert["S0"][] = "/";
        }
        
        foreach($this->convert as $k => $v)
        {
            print $k." -> ".implode(" | ",$v)."<br />";
        }
        print "<br />";
    }
}
?>

<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
<head>
<title>CFG -> Chomsky Normal Form Generator</title>
</head>
<body>
<h1>Context-Free Grammar to Chomsky Normal Form Generator</h1>

<?php
$submit = (isset($_POST['submit']) && $_POST['submit'] == 1) ? 1 : 0;
if($submit == 1)
{
?>
<a href="http://mpeveler.com/assets/content/projects/cfg2cnfREADME.txt">README</a> | <a href="http://mpeveler.com/assets/content/projects/cfg2cnf.txt">View Page Source</a><br /><br />
<?php
    $a = new convertor;
    $a->convert();
}
else
{
?>
<a href="cfg2cnfREADME.txt">README</a> | <a href="cfg2cnf.txt">View Page Source</a> | <a href="http://www.mpeveler.com/?content=work">Back to Site</a><br /><br />
Put grammar below, one grammar rule per line.<br />
<br />
Ex: A->a|aAa|/<br />
The / character is the empty string or epsilon for the convertor. Capital Letters are non-terminals while lowercase are terminals. Hard limit for non-terminals is number of letters in alphabet.<br /><br />
<form action="cfg2cnf.php" method="POST">
    <input type="hidden" name="submit" value="1" />
    <textarea name="grammar" cols="100" rows="30"></textarea><br />
    <input type="submit" value="Convert!" />
</form>
<?php
}
?>
</body>
</html>