<?php

namespace Illuminate\Foundation;

use Illuminate\Support\Collection;
use Illuminate\Support\Stringable;

/*
                                                   .~))>>
                                                  .~)>>
                                                .~))))>>>
                                              .~))>>             ___
                                            .~))>>)))>>      .-~))>>
                                          .~)))))>>       .-~))>>)>
                                        .~)))>>))))>>  .-~)>>)>
                    )                 .~))>>))))>>  .-~)))))>>)>
                 ( )@@*)             //)>))))))  .-~))))>>)>
               ).@(@@               //))>>))) .-~))>>)))))>>)>
             (( @.@).              //))))) .-~)>>)))))>>)>
           ))  )@@*.@@ )          //)>))) //))))))>>))))>>)>
        ((  ((@@@.@@             |/))))) //)))))>>)))>>)>
       )) @@*. )@@ )   (\_(\-\b  |))>)) //)))>>)))))))>>)>
     (( @@@(.@(@ .    _/`-`  ~|b |>))) //)>>)))))))>>)>
      )* @@@ )@*     (@)  (@) /\b|))) //))))))>>))))>>
    (( @. )@( @ .   _/  /    /  \b)) //))>>)))))>>>_._
     )@@ (@@*)@@.  (6///6)- / ^  \b)//))))))>>)))>>   ~~-.
  ( @jgs@@. @@@.*@_ VvvvvV//  ^  \b/)>>))))>>      _.     `bb
   ((@@ @@@*.(@@ . - | o |' \ (  ^   \b)))>>        .'       b`,
    ((@@).*@@ )@ )   \^^^/  ((   ^  ~)_        \  /           b `,
      (@@. (@@ ).     `-'   (((   ^    `\ \ \ \ \|             b  `.
        (*.@*              / ((((        \| | |  \       .       b `.
                          / / (((((  \    \ /  _.-~\     Y,      b  ;
                         / / / (((((( \    \.-~   _.`" _.-~`,    b  ;
                        /   /   `(((((()    )    (((((~      `,  b  ;
                      _/  _/      `"""/   /'                  ; b   ;
                  _.-~_.-~           /  /'                _.'~bb _.'
                ((((~~              / /'              _.'~bb.--~
                                   ((((          __.-~bb.-~
                                               .'  b .~~
                                               :bb ,'
                                               ~~~~
 */

class Inspiring
{
    /**
     * Get an inspiring quote.
     *
     * Taylor & Dayle made this commit from Jungfraujoch. (11,333 ft.)
     *
     * May McGinnis always control the board. #LaraconUS2015
     *
     * RIP Charlie - Feb 6, 2018
     *
     * @return string
     */
    public static function quote()
    {
        return static::quotes()
            ->map(fn ($quote) => static::formatForConsole($quote))
            ->random();
    }

    /**
     * Get the collection of inspiring quotes.
     *
     * @return \Illuminate\Support\Collection
     */
    public static function quotes()
    {
        return new Collection([
            'Do or do not. There is no try. - Yoda',
            'Fear is the path to the dark side. Fear leads to anger, anger leads to hate, hate leads to suffering. - Yoda',
            'In a dark place we find ourselves, and a little more knowledge lights our way. - Yoda',
            'The Force will be with you, always. - Obi-Wan Kenobi',
            'Your focus determines your reality. - Qui-Gon Jinn',
            'Train yourself to let go of everything you fear to lose. - Yoda',
            'The greatest teacher, failure is. - Yoda',
            'Pass on what you have learned. Strength, mastery. But weakness, folly, failure also. Yes, failure most of all. - Yoda',
            'Luminous beings are we, not this crude matter. - Yoda',
            'Never tell me the odds. - Han Solo',
            'The belonging you seek is not behind you, it is ahead. - Maz Kanata',
            'Hope is like the sun. If you only believe in it when you can see it, you\'ll never make it through the night. - Leia Organa',
            'Confronting fear is the destiny of a Jedi. Your destiny. - Luke Skywalker',
            'The Force is not a power you have. It\'s not about lifting rocks. It\'s the energy between all things. - Luke Skywalker',
            'We are what they grow beyond. That is the true burden of all masters. - Yoda',
            'Size matters not. Judge me by my size, do you? - Yoda',
            'Wars not make one great. - Yoda',
            'Difficult to see. Always in motion is the future. - Yoda',
            'You must unlearn what you have learned. - Yoda',
            'Control, control. You must learn control. - Yoda',
            'Truly wonderful, the mind of a child is. - Yoda',
            'Much to learn, you still have. - Yoda',
            'Patience you must have, my young Padawan. - Yoda',
            'Adventure. Excitement. A Jedi craves not these things. - Yoda',
            'Already know you that which you need. - Yoda',
            'Named must your fear be before banish it you can. - Yoda',
            'A Jedi uses the Force for knowledge and defense, never for attack. - Yoda',
            'The dark side clouds everything. Impossible to see, the future is. - Yoda',
            'Always two there are, no more, no less. A master and an apprentice. - Yoda',
            'Clear your mind must be, if you are to discover the real villains behind this plot. - Yoda',
            'The Force is strong with you. - Darth Vader',
            'I find your lack of faith disturbing. - Darth Vader',
            'The circle is now complete. When I left you, I was but the learner. Now I am the master. - Darth Vader',
            'You don\'t know the power of the dark side. - Darth Vader',
            'Search your feelings. You know it to be true. - Darth Vader',
            'Strike me down, and I will become more powerful than you can possibly imagine. - Obi-Wan Kenobi',
            'Who\'s the more foolish: the fool, or the fool who follows him? - Obi-Wan Kenobi',
            'Many of the truths we cling to depend greatly on our own point of view. - Obi-Wan Kenobi',
            'In my experience, there\'s no such thing as luck. - Obi-Wan Kenobi',
            'We must ship. - Taylor Otwell',
        ]);
    }

    /**
     * Formats the given quote for a pretty console output.
     *
     * @param  string  $quote
     * @return string
     */
    protected static function formatForConsole($quote)
    {
        [$text, $author] = (new Stringable($quote))->explode('-');

        return sprintf(
            "\n  <options=bold>“ %s ”</>\n  <fg=gray>— %s</>\n",
            trim($text),
            trim($author),
        );
    }
}
