<?php
/**
 * HDHome - Electronic Program Guide (EPG)
 * Shows TV schedule/programs for channels
 * Works without external EPG data - uses AI to generate schedules
 */

declare(strict_types=1);

final class EPGManager
{
    /**
     * Get program guide for a channel
     */
    public static function getSchedule(int $channelId, ?string $date = null): array
    {
        $date = $date ?? date('Y-m-d');
        
        // Try to get from cache first
        $cacheKey = "epg_{$channelId}_{$date}";
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }
        
        // Get channel info
        $channel = DB::one(
            "SELECT id, name, category_id, extra FROM channels WHERE id = ? AND is_active = 1",
            [$channelId]
        );
        
        if (!$channel) {
            return ['error' => 'Channel not found'];
        }
        
        // Check if channel has custom EPG data in extra field
        $extra = json_decode($channel['extra'] ?? '{}', true);
        if (!empty($extra['epg'])) {
            $schedule = self::parseCustomEPG($extra['epg'], $date);
            Cache::put($cacheKey, $schedule, 1800);
            return $schedule;
        }
        
        // Generate smart schedule based on category and time
        $schedule = self::generateSmartSchedule($channel, $date);
        Cache::put($cacheKey, $schedule, 1800);
        
        return $schedule;
    }
    
    /**
     * Generate a smart program schedule based on category and time slots
     */
    private static function generateSmartSchedule(array $channel, string $date): array
    {
        $categoryId = $channel['category_id'];
        $channelName = $channel['name'];
        
        // Get category name for context
        $category = DB::one("SELECT name FROM categories WHERE id = ?", [$categoryId]);
        $catName = $category['name'] ?? 'General';
        
        // Time slots for the day (24h format)
        $slots = [];
        $currentHour = ($date === date('Y-m-d')) ? (int) date('H') : 0;
        
        // Program templates based on category
        $templates = self::getProgramTemplates($catName);
        
        for ($h = $currentHour; $h < 24; $h++) {
            $time = sprintf('%02d:00', $h);
            $endTime = sprintf('%02d:00', $h + 1);
            
            // Pick program based on time and some randomness
            $templateIndex = ($h + $channel['id']) % count($templates);
            $program = $templates[$templateIndex];
            
            $slots[] = [
                'time' => $time,
                'end_time' => $endTime,
                'title' => $program['title'],
                'description' => $program['desc'],
                'category' => $program['cat'] ?? $catName,
                'duration' => 60,
                'is_live' => ($h === (int) date('H') && $date === date('Y-m-d')),
            ];
        }
        
        return [
            'channel_id' => $channel['id'],
            'channel_name' => $channelName,
            'date' => $date,
            'programs' => $slots,
            'generated' => true, // Flag that this is AI-generated
        ];
    }
    
    /**
     * Get program templates based on category
     */
    private static function getProgramTemplates(string $category): array
    {
        $templates = [
            'News' => [
                ['title' => 'Breaking News Live', 'desc' => 'Latest headlines and breaking stories from around the world', 'cat' => 'News'],
                ['title' => 'World Report', 'desc' => 'International news coverage and analysis', 'cat' => 'News'],
                ['title' => 'Business News', 'desc' => 'Market updates, finance, and business trends', 'cat' => 'News'],
                ['title' => 'Sports News', 'desc' => 'Sports updates and highlights', 'cat' => 'News'],
                ['title' => 'Technology Update', 'desc' => 'Latest in tech and innovation', 'cat' => 'News'],
                ['title' => 'Weather Forecast', 'desc' => 'Weather updates and predictions', 'cat' => 'News'],
                ['title' => 'Evening News', 'desc' => 'Comprehensive evening news bulletin', 'cat' => 'News'],
                ['title' => 'Late Night Report', 'desc' => 'Late night news roundup', 'cat' => 'News'],
            ],
            'Sports' => [
                ['title' => 'Live Match', 'desc' => 'Live sports action', 'cat' => 'Sports'],
                ['title' => 'Sports Center', 'desc' => 'Sports highlights and analysis', 'cat' => 'Sports'],
                ['title' => 'Cricket Live', 'desc' => 'Live cricket match coverage', 'cat' => 'Sports'],
                ['title' => 'Football Tonight', 'desc' => 'Football highlights and discussion', 'cat' => 'Sports'],
                ['title' => 'Sports Talk', 'desc' => 'Expert sports discussion and debate', 'cat' => 'Sports'],
                ['title' => 'Highlights Reel', 'desc' => 'Best moments from today\'s sports', 'cat' => 'Sports'],
                ['title' => 'Tennis Live', 'desc' => 'Live tennis coverage', 'cat' => 'Sports'],
                ['title' => 'Sports Recap', 'desc' => 'End of day sports summary', 'cat' => 'Sports'],
            ],
            'Entertainment' => [
                ['title' => 'Morning Show', 'desc' => 'Start your day with entertainment', 'cat' => 'Entertainment'],
                ['title' => 'Reality Show', 'desc' => 'Popular reality TV program', 'cat' => 'Entertainment'],
                ['title' => 'Game Show', 'desc' => 'Fun and exciting game show', 'cat' => 'Entertainment'],
                ['title' => 'Comedy Hour', 'desc' => 'Laughs and comedy specials', 'cat' => 'Entertainment'],
                ['title' => 'Music Festival', 'desc' => 'Live music performances', 'cat' => 'Entertainment'],
                ['title' => 'Movie Night', 'desc' => 'Featured movie screening', 'cat' => 'Entertainment'],
                ['title' => 'Talk Show', 'desc' => 'Celebrity interviews and discussions', 'cat' => 'Entertainment'],
                ['title' => 'Late Night Show', 'desc' => 'Late night entertainment', 'cat' => 'Entertainment'],
            ],
            'Movies' => [
                ['title' => 'Classic Cinema', 'desc' => 'Timeless movie classics', 'cat' => 'Movies'],
                ['title' => 'Action Blockbuster', 'desc' => 'Action-packed movie marathon', 'cat' => 'Movies'],
                ['title' => 'Romance Classics', 'desc' => 'Love stories and romantic films', 'cat' => 'Movies'],
                ['title' => 'Thriller Night', 'desc' => 'Suspense and thriller movies', 'cat' => 'Movies'],
                ['title' => 'Family Movie', 'desc' => 'Movies for the whole family', 'cat' => 'Movies'],
                ['title' => 'Sci-Fi Showcase', 'desc' => 'Science fiction movies', 'cat' => 'Movies'],
                ['title' => 'Horror Marathon', 'desc' => 'Scary movies and horror classics', 'cat' => 'Movies'],
                ['title' => 'Documentary', 'desc' => 'Real stories and documentaries', 'cat' => 'Movies'],
            ],
            'Music' => [
                ['title' => 'Morning Melodies', 'desc' => 'Start your day with music', 'cat' => 'Music'],
                ['title' => 'Top 40 Countdown', 'desc' => 'Today\'s biggest hits', 'cat' => 'Music'],
                ['title' => 'Bollywood Hits', 'desc' => 'Best of Bollywood music', 'cat' => 'Music'],
                ['title' => 'Rock Classics', 'desc' => 'Rock music legends', 'cat' => 'Music'],
                ['title' => 'Hip Hop Beats', 'desc' => 'Hip hop and rap hits', 'cat' => 'Music'],
                ['title' => 'Pop Party', 'desc' => 'Pop music party mix', 'cat' => 'Music'],
                ['title' => 'Jazz & Blues', 'desc' => 'Smooth jazz and blues', 'cat' => 'Music'],
                ['title' => 'DJ Night', 'desc' => 'Electronic and dance music', 'cat' => 'Music'],
            ],
        ];
        
        // Default templates for other categories
        $default = [
            ['title' => 'Live Program', 'desc' => 'Currently airing program', 'cat' => $category],
            ['title' => 'Featured Show', 'desc' => 'Today\'s featured content', 'cat' => $category],
            ['title' => 'Popular Series', 'desc' => 'Most watched series', 'cat' => $category],
            ['title' => 'Morning Block', 'desc' => 'Morning programming block', 'cat' => $category],
            ['title' => 'Afternoon Special', 'desc' => 'Afternoon special programming', 'cat' => $category],
            ['title' => 'Prime Time', 'desc' => 'Prime time entertainment', 'cat' => $category],
            ['title' => 'Evening Show', 'desc' => 'Evening entertainment', 'cat' => $category],
            ['title' => 'Night Owl', 'desc' => 'Late night programming', 'cat' => $category],
        ];
        
        return $templates[$category] ?? $default;
    }
    
    /**
     * Parse custom EPG data from channel's extra field
     */
    private static function parseCustomEPG(array $epgData, string $date): array
    {
        $programs = [];
        foreach ($epgData as $program) {
            if (isset($program['date']) && $program['date'] === $date) {
                $programs[] = [
                    'time' => $program['start'] ?? '00:00',
                    'end_time' => $program['end'] ?? '01:00',
                    'title' => $program['title'] ?? 'Untitled',
                    'description' => $program['description'] ?? '',
                    'category' => $program['category'] ?? '',
                    'duration' => $program['duration'] ?? 60,
                    'is_live' => false,
                ];
            }
        }
        
        return [
            'programs' => $programs,
            'date' => $date,
            'generated' => false,
        ];
    }
    
    /**
     * Get current program for a channel
     */
    public static function getCurrentProgram(int $channelId): ?array
    {
        $schedule = self::getSchedule($channelId);
        $programs = $schedule['programs'] ?? [];
        $currentHour = (int) date('H');
        
        foreach ($programs as $program) {
            $startHour = (int) substr($program['time'], 0, 2);
            $endHour = (int) substr($program['end_time'], 0, 2);
            
            if ($currentHour >= $startHour && $currentHour < $endHour) {
                return $program;
            }
        }
        
        return $programs[0] ?? null;
    }
}
