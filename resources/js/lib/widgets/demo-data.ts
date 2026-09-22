import type {
    CalendarEventDemo,
    NewsItemDemo,
    WeatherDemoData,
} from '@/lib/widgets/types';

export const DEMO_WEATHER: WeatherDemoData = {
    location: 'Nottingham, United Kingdom',
    temp: 18,
    units: 'c',
    condition: 'Partly cloudy',
    high: 21,
    low: 12,
    icon: 'partly-cloudy',
};

export const DEMO_NEWS_ITEMS: NewsItemDemo[] = [
    {
        title: 'Local markets open higher as confidence returns',
        source: 'DZ News',
        publishedAt: '2026-09-15T08:30:00Z',
    },
    {
        title: 'City announces new public transport schedule',
        source: 'Metro Wire',
        publishedAt: '2026-09-15T07:15:00Z',
    },
    {
        title: 'Weekend festival expected to draw record crowds',
        source: 'Culture Desk',
        publishedAt: '2026-09-14T18:00:00Z',
    },
    {
        title: 'Technology firms expand regional hiring plans',
        source: 'Biz Daily',
        publishedAt: '2026-09-14T12:45:00Z',
    },
    {
        title: 'Weather outlook: mild temperatures through Friday',
        source: 'Climate Now',
        publishedAt: '2026-09-14T09:00:00Z',
    },
];

export const DEMO_CALENDAR_EVENTS: CalendarEventDemo[] = [
    {
        title: 'Team Standup',
        startsAt: '2026-09-15T09:00:00Z',
        endsAt: '2026-09-15T09:30:00Z',
        location: 'Room A',
    },
    {
        title: 'Product Demo',
        startsAt: '2026-09-15T14:00:00Z',
        endsAt: '2026-09-15T15:00:00Z',
        location: 'Main Hall',
    },
    {
        title: 'Community Evening',
        startsAt: '2026-09-16T18:30:00Z',
        endsAt: '2026-09-16T21:00:00Z',
        location: 'Lobby',
    },
    {
        title: 'All-hands Meeting',
        startsAt: '2026-09-17T10:00:00Z',
        endsAt: '2026-09-17T11:00:00Z',
        location: 'Auditorium',
    },
    {
        title: 'Board Review',
        startsAt: '2026-09-18T13:00:00Z',
        endsAt: '2026-09-18T15:00:00Z',
        location: 'Conference 2',
    },
];
