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

        /* remove extra spaces created */
        $text = preg_replace('/\s+/',' ',$text);

        $text = trim($text);
        $words = explode(" ", $text);
        foreach ($words as $value)
        {
            $temp = trim($value);
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
			return $result;
		}
	}
}
?>
