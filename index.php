<?php

// auto load sentiment lib
require_once __DIR__ . '/autoload.php';
$sentiment = new \PHPInsight\Sentiment();

//////////////////////////////////////////////////////
//
//		Getting all the content from reddit, running sentiment analysis and restructuring
//
//////////////////////////////////////////////////////

// reddit url for all 
$redditUrl = "https://www.reddit.com/r/all.json";

// get file contents from the above url and then json decode it
$redditFileContents = json_decode(file_get_contents($redditUrl));

// reddit comment data
$redditCommentData = $redditFileContents->data->children;

// store data in an array to make it easier to work with
$formattedResponseData = array();

// loop through all the result data
foreach($redditCommentData as $index=>$value) 
{
	$title = strtolower($value->data->title);
	$link = $value->data->permalink;
	$commentTime = $value->data->created;
	$realTime = gmdate("Y-m-d H:i:s", $commentTime);

	$score = $sentiment->score($title);
	$quickRating = array_search(max($score), $score); // get the highest sentiment value

	if (array_search(max($score), $score) == "pos") 
	{
		$quickRating = "positive";
	}
	else if (array_search(max($score), $score) == "neg")
	{
		$quickRating = "negative";
	}
	else
	{
		$quickRating = "neutral";
	}
	
	$formattedResponseData[] = array(
										"title" => $title, 
										"link" => $link, 
										"commentTime" => $commentTime, 
										"realTime" => $realTime,
										"scoreNeu" => $score['neu'], 
										"scorePos" => $score['pos'], 
										"scoreNeg" => $score['neg'],
										"overallSentiment" => $quickRating
									);
}

//////////////////////////////////////////////////////
//
//		Search through all the titles and split into separate arrays, then merge the two so that search terms show at the top
//
//////////////////////////////////////////////////////

// search term
$searchTerm = "";
$dataWithSearchTerm = array();
$dataWithoutSearchTerm = array();
$dataSearchTermOutput = $formattedResponseData; // set this to have a default since all data output will use this

$fromDateDay = "";
$fromDateMonth = "";
$fromDateYear = "";

$toDateDay = "";
$toDateMonth = "";
$toDateYear = "";

// only run if post has values
if (count($_POST) > 0) 
{
	// get posted search term
	$searchTerm = strtolower($_POST['searchTerm']);

	// search through the array, separated this since this is not a requirement
	foreach ($formattedResponseData as $index=>$value)
	{
		if (strpos($value['title'], $searchTerm) !== false)
		{
			$dataWithSearchTerm[] = $formattedResponseData[$index];
		} 
		else 
		{
			$dataWithoutSearchTerm[] = $formattedResponseData[$index];
		}
	}

	// combine the search results, matching first
	$dataSearchTermOutput = array_merge($dataWithSearchTerm, $dataWithoutSearchTerm);

	if (isset($_POST['sentimentFilter']) && $_POST['sentimentFilter'] != '') 
	{
		foreach ($dataSearchTermOutput as $index=>$value) 
		{
			if (trim($value['overallSentiment']) != trim($_POST['sentimentFilter']))
			{
				unset($dataSearchTermOutput[$index]);
			}
		}
	}

	// filter section
	if (isset($_POST['fromDateDay']) && isset($_POST['fromDateMonth']) && isset($_POST['fromDateYear']) && isset($_POST['toDateDay']) && isset($_POST['toDateMonth']) && isset($_POST['toDateYear'])) 
	{
		$fromDateDay = $_POST['fromDateDay'];
		$fromDateMonth = $_POST['fromDateMonth'];
		$fromDateYear = $_POST['fromDateYear'];
		$toDateDay = $_POST['toDateDay'];
		$toDateMonth = $_POST['toDateMonth'];
		$toDateYear = $_POST['toDateYear'];

		$fromDate = strtotime($fromDateDay . "-" . $fromDateMonth . "-" . $fromDateYear . " 00:00");
		$toDate = strtotime($toDateDay . "-" . $toDateMonth . "-" . $toDateYear . " 24:00");

		if (!empty($fromDate) && !empty($toDate))
		{
			foreach ($dataSearchTermOutput as $index=>$value) 
			{
				if ($value['commentTime'] < $fromDate || $value['commentTime'] > $toDate) 
				{
					unset($dataSearchTermOutput[$index]);
				}
			}
		}
	}
}

?>

<!DOCTYPE html>
<html>
	<head>
		<title>Reddit Sentiment Analysis and Search</title>
		<link rel="stylesheet" type="text/css" href="styles.css">
	</head>
	<body>
		<div class="form">
			<form method="post" name="filterForm">
				<input type="text" name="searchTerm" placeholder="Search term" value="<?php echo $searchTerm; ?>"/>
				<input type="submit" name="submit" value="Search"/>
				<hr/>
				<select name="sentimentFilter">
					<option value=""> Sentiment Filter </option>
					<option value="positive" <?php if (isset($_POST['sentimentFilter']) && $_POST['sentimentFilter'] == 'positive') { echo 'selected'; } ?> >Positive</option>
					<option value="neutral" <?php if (isset($_POST['sentimentFilter']) && $_POST['sentimentFilter'] == 'neutral') { echo 'selected'; } ?> >Neutral</option>
					<option value="negative" <?php if (isset($_POST['sentimentFilter']) && $_POST['sentimentFilter'] == 'negative') { echo 'selected'; } ?> >Negative</option>
				</select>
				<br/><br/>
				Filter from date<br/>
				Day : <input type="number" name="fromDateDay" value="<?php echo $fromDateDay; ?>"/>
				Month : <input type="number" name="fromDateMonth" value="<?php echo $fromDateMonth; ?>"/>
				Year : <input type="number" name="fromDateYear" value="<?php echo $fromDateYear; ?>"/>
				<br/>
				Filter to date<br/>
				Day : <input type="number" name="toDateDay" value="<?php echo $toDateDay; ?>"/>
				Month : <input type="number" name="toDateMonth" value="<?php echo $toDateMonth; ?>"/>
				Year : <input type="number" name="toDateYear" value="<?php echo $toDateYear; ?>"/>
				<br/>
				<input type="submit" value="Apply Filter"/>
				<hr/>
			</form>
		</div>
		<?php foreach($dataSearchTermOutput as $index=>$value) : ?>
			<div class="border <?php echo $value['overallSentiment']; ?>">
				<h3>
					<?php echo ucfirst($value['title']); ?>
				</h3>
				<label>
					Created at : <?php echo $value['realTime']; ?>
				</label>
				 | 
				<label>
					Sentiment : <?php echo $value['overallSentiment']; ?>
				</label>
				<a href="https://www.reddit.com/<?php echo $value['link']; ?>" target="_blank">Comments</a>
			</div>
		<?php endforeach; ?>
	</body>
</html>