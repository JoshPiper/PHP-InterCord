<?php



require __DIR__ . '/vendor/autoload.php';

$wh = new \Internet\InterCord\Webhook($_SERVER['DISCORD_URL']);
$wh->execute('this is a test using strings', 'test username', 'https://github.githubassets.com/images/modules/logos_page/GitHub-Mark.png');
$wh->execute((new \Internet\InterCord\RichEmbed())
	->setTitle('this is also a test embed.')
	->setDescription('i like testing')
	->setFooter('yeet')
);
$wh->execute([
	'i can do it with both???',
	(new \Internet\InterCord\RichEmbed())
		->setTitle('this is also a test embed.')
		->setDescription('i like testing')
		->setFooter('yeet'),
	(new \Internet\InterCord\RichEmbed())
		->setTitle('i need to pee')
		->setDescription('im an albatroz.')
		->setFooter('yeet')
		->setColor('#BB00BB')
	],
	'and usernames?!'
);