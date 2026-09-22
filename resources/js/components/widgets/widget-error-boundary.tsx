import { Component, type ErrorInfo, type ReactNode } from 'react';

type Props = {
    children: ReactNode;
    className?: string;
};

type State = {
    hasError: boolean;
};

export class WidgetErrorBoundary extends Component<Props, State> {
    state: State = { hasError: false };

    static getDerivedStateFromError(): State {
        return { hasError: true };
    }

    componentDidCatch(error: Error, info: ErrorInfo): void {
        console.error('Widget render failed', error, info);
    }

    componentDidUpdate(prevProps: Props): void {
        if (prevProps.children !== this.props.children && this.state.hasError) {
            this.setState({ hasError: false });
        }
    }

    render(): ReactNode {
        if (this.state.hasError) {
            return (
                <div
                    className={
                        this.props.className ??
                        'flex h-full w-full items-center justify-center bg-black/40 px-3 text-center text-sm text-white/80'
                    }
                    data-test="widget-unavailable"
                >
                    Widget unavailable
                </div>
            );
        }

        return this.props.children;
    }
}
