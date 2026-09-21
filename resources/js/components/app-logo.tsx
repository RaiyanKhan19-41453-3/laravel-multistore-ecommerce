import { useStore } from '@/lib/store';
import AppLogoIcon from './app-logo-icon';

export default function AppLogo() {
    const store = useStore();

    return (
        <>
            <div className="flex aspect-square size-8 shrink-0 items-center justify-center overflow-hidden rounded-md">
                {store.logo ? (
                    <img src={store.logo} alt={store.name} className="h-full w-full object-cover" />
                ) : (
                    <AppLogoIcon className="size-5 fill-current text-sidebar-foreground" />
                )}
            </div>
            <div className="ml-1 grid flex-1 text-left text-sm group-data-[collapsible=icon]:hidden">
                <span className="mb-0.5 truncate leading-none font-semibold">{store.name}</span>
            </div>
        </>
    );
}
