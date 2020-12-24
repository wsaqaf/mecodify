<?php

/* array sort helper function */
function randomSort($a, $b)
{
    return rand(-1, 1);
}

class PTagCloud
{
	var $m_arTags = array();

	//custom parameters
	var $m_displayedElementsCount;
	var $m_searchURL;
	var $m_backgroundImage;
	var $m_backgroundColor;
	var $m_width;
	var $m_arColors;
	var $m_bUTF8;

	function __construct($displayedElementCount, $arSeedWords = false)
	{
	    $this->m_displayedElementsCount = $displayedElementCount;
	    $this->m_searchURL = "";
	    $this->m_bUTF8 = false;
	    $this->m_backgroundColor = "#FFFFFF";
	    $this->m_arColors[0] = "#5122CC";
	    $this->m_arColors[1] = "#229926";
	    $this->m_arColors[2] = "#330099";
	    $this->m_arColors[3] = "#819922";
	    $this->m_arColors[4] = "#22CCC3";
	    $this->m_arColors[5] = "#99008D";
	    $this->m_arColors[6] = "#943131";
	    $this->m_arColors[7] = "#B23B3B";
	    $this->m_arColors[8] = "#229938";
	    $this->m_arColors[9] = "#419922";

		if ($arSeedWords !== false && is_array($arSeedWords))
		{
			foreach ($arSeedWords as $key => $value)
			{
				$this->addTag($value);
			}
		}
	}

	function PTagCloud($displayedElementCount, $arSeedWords = false)
	{
		$this->__construct($displayedElementCount, $arSeedWords);
	}

	function setSearchURL($searchURL)
	{
	    $this->m_searchURL = $searchURL;
	}

	function setUTF8($bUTF8)
	{
	    $this->m_bUTF8 = $bUTF8;
	}

	function setWidth($width)
	{
	    $this->m_width = $width;
	}

	function setBackgroundImage($backgroundImage)
	{
	    $this->m_backgroundImage = $backgroundImage;
	}

	function setBackgroundColor($backgroundColor)
	{
	    $this->m_backgroundColor = $backgroundColor;
	}

	function setTextColors($arColors)
	{
	    $this->m_arColors = $arColors;
	}

	/* word replace helper */
    function str_replace_word($needle, $replacement, $haystack)
    {
        $pattern = "/\b$needle\b/i";
        $haystack = preg_replace($pattern, $replacement, $haystack);
        return $haystack;
    }

    function keywords_extract($text)
    {
        $text = strtolower($text);
        $text = strip_tags($text);

        /*
         * Handle common words first because they have punctuation and we need to remove them
         * before removing punctuation.
         */
/*        $commonWords = "'tis,'twas,a,able,about,across,after,ain't,all,almost,also,am,among,an,and,any,are,aren't," .
            "as,at,be,because,been,but,by,can,can't,cannot,could,could've,couldn't,dear,did,didn't,do,does,doesn't," .
            "don't,either,else,ever,every,for,from,get,got,had,has,hasn't,have,he,he'd,he'll,he's,her,hers,him,his," .
            "how,how'd,how'll,how's,however,i,i'd,i'll,i'm,i've,if,in,into,is,isn't,it,it's,its,just,least,let,like," .
            "likely,may,me,might,might've,mightn't,most,must,must've,mustn't,my,neither,no,nor,not,o'clock,of,off," .
            "often,on,only,or,other,our,own,rather,said,say,says,shan't,she,she'd,she'll,she's,should,should've," .
            "shouldn't,since,so,some,than,that,that'll,that's,the,their,them,then,there,there's,these,they,they'd," .
            "they'll,they're,they've,this,tis,to,too,twas,us,wants,was,wasn't,we,we'd,we'll,we're,were,weren't,what," .
            "what'd,what's,when,when,when'd,when'll,when's,where,where'd,where'll,where's,which,while,who,who'd," .
            "who'll,who's,whom,why,why'd,why'll,why's,will,with,won't,would,would've,wouldn't,yet,you,you'd,you'll," .
            "you're,you've,your";
        $commonWords = strtolower($commonWords);
        $commonWords = explode(",", $commonWords);
//echo "<hr>$text<hr>";
      foreach($commonWords as $commonWord)
        {
            $text = $this->str_replace_word($commonWord, "", $text);
        }
        */
/*
        if ($this->m_bUTF8)
            $text = preg_replace('/[^\p{L}0-9\s]|\n|\r/u',' ',$text);
        else
            $text = preg_replace('/[^a-zA-Z0-9\s]|\n|\r/',' ',$text);
*/
        /* remove extra spaces created */
        $text = preg_replace('/\s+/',' ',$text);

        $text = trim($text);
        $words = explode(" ", $text);
        foreach ($words as $value)
        {
            $temp = trim($value);
//            if (is_numeric($temp))
//                continue;
            $keywords[] = $temp;
        }

        return $keywords;
    }

    function addTagsFromText($SeedText)
    {
        $words = $this->keywords_extract($SeedText);
		foreach ($words as $key => $value)
		{
			$this->addTag($value);
		}
    }

	function addTag($tag, $useCount = 1)
	{
//		$tag = strtolower($tag);
		if (array_key_exists($tag, $this->m_arTags))
			$this->m_arTags[$tag] += $useCount;
		else
			$this->m_arTags[$tag] = $useCount;
	}

	function gradeFrequency($frequency)
	{
	    $grade = 0;
		if ($frequency >= 80)
			$grade = 8;
		else if ($frequency >= 70)
			$grade = 8;
		else if ($frequency >= 60)
			$grade = 7;
		else if ($frequency >= 50)
			$grade = 6;
		else if ($frequency >= 40)
			$grade = 5;
		else if ($frequency >= 30)
			$grade = 4;
		else if ($frequency >= 20)
			$grade = 3;
		else if ($frequency >= 10)
			$grade = 2;
		else if ($frequency >= 5)
			$grade = 1;

		return $grade;
	}

	function emitCloud($bHTML = true)
	{
	    arsort($this->m_arTags);
	    $arTopTags = array_slice($this->m_arTags, 0, $this->m_displayedElementsCount);

	    /* randomize the order of elements */
//		uasort($arTopTags, 'randomSort');

		$this->maxCount = max($this->m_arTags);
		if (is_array($this->m_arTags))
		{
			if ($bHTML)
			    $result = '<div id="id_tag_cloud" style="' . (isset($this->m_width) ? ("width:". $this->m_width. ";") : "") .
			               'line-height:normal"><div style="border-style:solid;border-width:1px;' .
			              (isset($this->m_backgroundImage) ? ("background:url('". $this->m_backgroundImage ."');") : "") .
			              'border-color:#888;margin-top:20px;margin-bottom:10px;padding:5px 5px 20px 5px;background-color:'.$this->m_backgroundColor.';">';
            else
                $result = array();
			foreach ($arTopTags as $tag => $useCount)
			{
				$grade = $this->gradeFrequency(($useCount * 100) / $this->maxCount);
				if ($bHTML)
				{
          $t=ltrim($tag,'#');
					$result .= ("<a href='#' onclick=javascript:go_to_hashtag('$t') title=\"More on " .
					    $tag."\" style=\"color:".$this->m_arColors[$grade].";\">" .
					    "<span style=\"color:".$this->m_arColors[$grade]."; letter-spacing:1px; ".
					    "padding:4px; font-family:Times; font-weight:900; font-size:" .
					    (1+0.2 * $grade) . "em\">".$tag."</span></a> ");
				}
				else
				    $result[$tag] = $grade;
			}
/*
			if ($bHTML)
                $result .= ('</div><div style="position:relative;top:-25px">' .
                           '<div style="float:right;padding-right:5px;height:15px;font-size:10px">' .
                           '<a style="color:#777" target="_blank" href="http://www.softwaremastercenter.com">free shareware downloads</a>' .
                           '</div></div></div><br />');
*/
			return $result;
		}
	}
}
?>
