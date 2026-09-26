import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { useAppearance } from '@/hooks/use-appearance';
import { Gem, Monitor, Moon, Palette, Sun, Waves } from 'lucide-react';
import { HTMLAttributes } from 'react';

export default function AppearanceToggleDropdown({ className = '', ...props }: HTMLAttributes<HTMLDivElement>) {
    const { appearance, updateAppearance } = useAppearance();

    const getCurrentIcon = () => {
        switch (appearance) {
            case 'dark':
                return <Moon className="h-5 w-5" />;
            case 'light':
                return <Sun className="h-5 w-5" />;
            case 'brand':
                return <Palette className="h-5 w-5" />;
            case 'ocean':
                return <Waves className="h-5 w-5" />;
            case 'beauty':
                return <Gem className="h-5 w-5" />;
            default:
                return <Monitor className="h-5 w-5" />;
        }
    };

    return (
        <div className={className} {...props}>
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button variant="ghost" size="icon" className="h-9 w-9 rounded-md">
                        {getCurrentIcon()}
                        <span className="sr-only">Toggle theme</span>
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                    <DropdownMenuItem onClick={() => updateAppearance('light')}>
                        <span className="flex items-center gap-2">
                            <Sun className="h-5 w-5" />
                            Light
                        </span>
                    </DropdownMenuItem>
                    <DropdownMenuItem onClick={() => updateAppearance('dark')}>
                        <span className="flex items-center gap-2">
                            <Moon className="h-5 w-5" />
                            Dark
                        </span>
                    </DropdownMenuItem>
                    <DropdownMenuItem onClick={() => updateAppearance('brand')}>
                        <span className="flex items-center gap-2">
                            <Palette className="h-5 w-5" />
                            Brand
                        </span>
                    </DropdownMenuItem>
                    <DropdownMenuItem onClick={() => updateAppearance('ocean')}>
                        <span className="flex items-center gap-2">
                            <Waves className="h-5 w-5" />
                            Ocean
                        </span>
                    </DropdownMenuItem>
                    <DropdownMenuItem onClick={() => updateAppearance('beauty')}>
                        <span className="flex items-center gap-2">
                            <Gem className="h-5 w-5" />
                            Beauty
                        </span>
                    </DropdownMenuItem>
                    <DropdownMenuItem onClick={() => updateAppearance('system')}>
                        <span className="flex items-center gap-2">
                            <Monitor className="h-5 w-5" />
                            System
                        </span>
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
        </div>
    );
}
