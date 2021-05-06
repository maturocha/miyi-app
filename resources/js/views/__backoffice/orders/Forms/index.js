import loadable from '@loadable/component';

export const MeetupForm = loadable(() => import('./MeetupForm'));
export const ClientForm = loadable(() => import('./Client'));
export const ItemsForm = loadable(() => import('./Items'));

