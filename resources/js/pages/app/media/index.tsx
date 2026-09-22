import { Head, router, useForm, usePage } from '@inertiajs/react';
import {
    ChevronDown,
    Copy,
    Download,
    Eye,
    FileText,
    Folder,
    Grid3X3,
    Image,
    Link2,
    List,
    MoreHorizontal,
    Pencil,
    Plus,
    Search,
    Sparkles,
    Trash2,
    Type,
    Upload,
    Video,
} from 'lucide-react';
import {
    useEffect,
    useRef,
    useState,
    type DragEvent,
    type FormEvent,
    type KeyboardEvent,
    type ReactNode,
} from 'react';
import AiImageDialog from '@/components/ai/ai-image-dialog';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { EmptyState } from '@/components/ui/empty-state';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatBytes, formatRelativeDate } from '@/lib/format-bytes';
import { cn } from '@/lib/utils';
import { media } from '@/routes/app';
import mediaRoutes from '@/routes/app/media';
import type {
    MediaCounts,
    MediaFilters,
    MediaListItem,
    MediaSortValue,
    MediaTypeValue,
} from '@/types/media';

type FileMediaType = 'image' | 'video' | 'logo' | 'document';

type PaginatedMedia = {
    data: MediaListItem[];
    meta: {
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number | null;
        to: number | null;
    };
    links: {
        first: string | null;
        last: string | null;
        prev: string | null;
        next: string | null;
    };
};

type Props = {
    media: PaginatedMedia;
    filters: MediaFilters;
    counts: MediaCounts;
    total_bytes: number;
    types: { value: MediaTypeValue; label: string }[];
};

type FilterTab = MediaFilters['type'];

const TYPE_TABS: {
    id: FilterTab;
    label: string;
    countKey: keyof MediaCounts;
}[] = [
    { id: 'all', label: 'All', countKey: 'all' },
    { id: 'image', label: 'Images', countKey: 'image' },
    { id: 'video', label: 'Videos', countKey: 'video' },
    { id: 'text', label: 'Text', countKey: 'text' },
    { id: 'logo', label: 'Logos', countKey: 'logo' },
    { id: 'document', label: 'Documents', countKey: 'document' },
    { id: 'link', label: 'Links', countKey: 'link' },
];

const SORT_OPTIONS: { value: MediaSortValue; label: string }[] = [
    { value: 'newest', label: 'Newest' },
    { value: 'oldest', label: 'Oldest' },
    { value: 'name_asc', label: 'Name A–Z' },
    { value: 'name_desc', label: 'Name Z–A' },
    { value: 'largest', label: 'Largest' },
    { value: 'smallest', label: 'Smallest' },
];

const FILE_ACCEPT: Record<FileMediaType, string> = {
    image: '.jpg,.jpeg,.png,.webp,.gif,image/jpeg,image/png,image/webp,image/gif',
    video: '.mp4,.mov,.webm,video/mp4,video/quicktime,video/webm',
    logo: '.jpg,.jpeg,.png,.webp,.gif,.svg,image/jpeg,image/png,image/webp,image/gif,image/svg+xml',
    document:
        '.pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document',
};

const DROPZONE_ACCEPT = [
    FILE_ACCEPT.image,
    FILE_ACCEPT.video,
    FILE_ACCEPT.logo,
    FILE_ACCEPT.document,
].join(',');

const selectClassName = cn(
    'border-input flex h-9 w-full rounded-md border bg-transparent px-3 py-1 text-sm shadow-xs outline-none',
    'focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]',
    'disabled:cursor-not-allowed disabled:opacity-50',
);

const textareaClassName = cn(
    'border-input flex min-h-24 w-full rounded-md border bg-transparent px-3 py-2 text-sm shadow-xs outline-none',
    'placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px]',
    'disabled:cursor-not-allowed disabled:opacity-50',
);

function stripExtension(filename: string): string {
    return filename.replace(/\.[^.]+$/, '');
}

function inferMediaType(file: File): FileMediaType | null {
    const ext = file.name.split('.').pop()?.toLowerCase() ?? '';

    if (
        ['mp4', 'mov', 'webm'].includes(ext) ||
        file.type.startsWith('video/')
    ) {
        return 'video';
    }

    if (
        ['pdf', 'doc', 'docx'].includes(ext) ||
        file.type.includes('pdf') ||
        file.type.includes('word')
    ) {
        return 'document';
    }

    if (ext === 'svg' || file.type === 'image/svg+xml') {
        return 'logo';
    }

    if (
        ['jpg', 'jpeg', 'png', 'webp', 'gif'].includes(ext) ||
        file.type.startsWith('image/')
    ) {
        return 'image';
    }

    return null;
}

function typeIcon(type: MediaTypeValue, className = 'size-4') {
    const icons = {
        image: Image,
        video: Video,
        text: Type,
        logo: Folder,
        document: FileText,
        link: Link2,
    } as const;
    const Icon = icons[type] ?? Image;
    return <Icon className={className} aria-hidden />;
}

function typeBadgeClass(type: MediaTypeValue): string {
    switch (type) {
        case 'video':
            return 'bg-violet-500/15 text-violet-700 dark:text-violet-300';
        case 'logo':
            return 'bg-amber-500/15 text-amber-700 dark:text-amber-300';
        case 'text':
            return 'bg-violet-500/15 text-violet-700 dark:text-violet-300';
        case 'link':
            return 'bg-cyan-500/15 text-cyan-700 dark:text-cyan-300';
        case 'document':
            return 'bg-sky-500/15 text-sky-700 dark:text-sky-300';
        default:
            return 'bg-foreground/10 text-foreground';
    }
}

function emptyCopy(type: FilterTab): { title: string; description: string } {
    switch (type) {
        case 'image':
            return {
                title: 'No images yet',
                description:
                    'Upload JPG, PNG, WebP, or GIF files to reuse across designs.',
            };
        case 'video':
            return {
                title: 'No videos yet',
                description:
                    'Upload MP4, MOV, or WebM clips for your playlists and screens.',
            };
        case 'text':
            return {
                title: 'No text items yet',
                description:
                    'Save reusable text snippets for announcements and messages.',
            };
        case 'logo':
            return {
                title: 'No logos yet',
                description:
                    'Upload brand marks (including SVG) for consistent branding.',
            };
        case 'document':
            return {
                title: 'No documents yet',
                description:
                    'Upload PDF or Word files for signage reference content.',
            };
        case 'link':
            return {
                title: 'No links yet',
                description:
                    'Save website or stream URLs as reusable media links.',
            };
        default:
            return {
                title: 'No media yet',
                description:
                    'Add images, videos, text, logos, documents, or links to build your library.',
            };
    }
}

function MediaPreview({ item }: { item: MediaListItem }) {
    if (item.type === 'image' || item.type === 'logo') {
        if (!item.preview_url) {
            return (
                <div className="bg-muted text-muted-foreground flex aspect-video items-center justify-center rounded-lg">
                    {typeIcon(item.type, 'size-10')}
                </div>
            );
        }

        return (
            <img
                src={item.preview_url}
                alt={item.name}
                className="bg-muted max-h-[60vh] w-full rounded-lg object-contain"
            />
        );
    }

    if (item.type === 'video') {
        if (!item.preview_url) {
            return (
                <div className="bg-muted text-muted-foreground flex aspect-video items-center justify-center rounded-lg">
                    <Video className="size-10" aria-hidden />
                </div>
            );
        }

        return (
            <video
                src={item.preview_url}
                controls
                className="bg-muted max-h-[60vh] w-full rounded-lg"
                poster={undefined}
            >
                Your browser does not support video playback.
            </video>
        );
    }

    if (item.type === 'text') {
        return (
            <div className="bg-muted max-h-[60vh] overflow-auto rounded-lg p-4">
                <p className="text-foreground text-sm leading-relaxed whitespace-pre-wrap">
                    {item.text_content || '—'}
                </p>
            </div>
        );
    }

    if (item.type === 'document') {
        const isPdf =
            item.mime_type === 'application/pdf' ||
            item.extension?.toLowerCase() === 'pdf';

        if (isPdf && item.preview_url) {
            return (
                <iframe
                    title={item.name}
                    src={item.preview_url}
                    className="bg-muted h-[60vh] w-full rounded-lg border"
                />
            );
        }

        return (
            <div className="bg-muted text-muted-foreground flex aspect-video flex-col items-center justify-center gap-2 rounded-lg">
                <FileText className="size-10" aria-hidden />
                <p className="text-sm">{item.original_filename ?? item.name}</p>
                {item.download_url ? (
                    <Button asChild variant="outline" size="sm">
                        <a href={item.download_url}>Download document</a>
                    </Button>
                ) : null}
            </div>
        );
    }

    return (
        <div className="bg-muted flex aspect-video flex-col items-center justify-center gap-3 rounded-lg p-6 text-center">
            <Link2 className="text-primary size-10" aria-hidden />
            <p className="text-foreground text-sm font-medium">{item.name}</p>
            <p className="text-muted-foreground max-w-full truncate font-mono text-xs">
                {item.url}
            </p>
            {item.url ? (
                <Button asChild size="sm">
                    <a href={item.url} target="_blank" rel="noreferrer">
                        Open external link
                    </a>
                </Button>
            ) : null}
        </div>
    );
}

function CardPreview({ item }: { item: MediaListItem }) {
    if (item.type === 'text') {
        return (
            <div className="bg-muted relative flex aspect-square items-center justify-center p-3">
                <p className="text-foreground line-clamp-5 text-center text-xs leading-relaxed">
                    {item.text_content || '—'}
                </p>
            </div>
        );
    }

    if (item.type === 'link') {
        return (
            <div className="bg-muted relative flex aspect-square flex-col items-center justify-center gap-2 p-3">
                <Link2 className="text-primary size-8" aria-hidden />
                <p className="text-muted-foreground w-full truncate text-center font-mono text-[10px]">
                    {item.url}
                </p>
            </div>
        );
    }

    if (item.type === 'document') {
        return (
            <div className="bg-muted relative flex aspect-square flex-col items-center justify-center gap-2">
                <FileText
                    className="text-muted-foreground size-10"
                    aria-hidden
                />
                <span className="text-muted-foreground font-mono text-[10px] uppercase">
                    {item.extension ?? 'doc'}
                </span>
            </div>
        );
    }

    if (item.type === 'video') {
        return (
            <div className="bg-muted relative aspect-square overflow-hidden">
                {item.preview_url ? (
                    <video
                        src={item.preview_url}
                        className="size-full object-cover"
                        muted
                        playsInline
                        preload="metadata"
                    />
                ) : (
                    <div className="text-muted-foreground flex size-full items-center justify-center">
                        <Video className="size-10" aria-hidden />
                    </div>
                )}
            </div>
        );
    }

    return (
        <div className="bg-muted relative aspect-square overflow-hidden">
            {item.preview_url ? (
                <img
                    src={item.preview_url}
                    alt={item.name}
                    className="size-full object-cover transition-transform duration-300 group-hover:scale-105"
                />
            ) : (
                <div className="text-muted-foreground flex size-full items-center justify-center">
                    {typeIcon(item.type, 'size-10')}
                </div>
            )}
        </div>
    );
}

function UploadProgress({
    progress,
}: {
    progress: { percentage?: number | null } | null | undefined;
}) {
    if (!progress || progress.percentage == null) {
        return null;
    }

    return (
        <div className="space-y-1.5" aria-live="polite">
            <div className="text-muted-foreground flex justify-between text-xs">
                <span>Uploading…</span>
                <span>{Math.round(progress.percentage)}%</span>
            </div>
            <div className="bg-secondary h-2 overflow-hidden rounded-full">
                <div
                    className="bg-primary h-full transition-all"
                    style={{ width: `${progress.percentage}%` }}
                />
            </div>
        </div>
    );
}

export default function MediaLibrary({
    media: paginated,
    filters,
    counts,
    total_bytes,
}: Props) {
    const { workspace, ai } = usePage().props;
    const permissions = workspace?.permissions;
    const canManage = permissions?.can_manage_media ?? false;
    const canDelete = permissions?.can_delete_media ?? false;

    const [searchInput, setSearchInput] = useState(filters.q);
    const [dragOver, setDragOver] = useState(false);

    const [previewItem, setPreviewItem] = useState<MediaListItem | null>(null);
    const [renameItem, setRenameItem] = useState<MediaListItem | null>(null);
    const [editItem, setEditItem] = useState<MediaListItem | null>(null);
    const [replaceItem, setReplaceItem] = useState<MediaListItem | null>(null);
    const [deleteItem, setDeleteItem] = useState<MediaListItem | null>(null);

    const [uploadOpen, setUploadOpen] = useState(false);
    const [uploadType, setUploadType] = useState<FileMediaType>('image');
    const [textOpen, setTextOpen] = useState(false);
    const [linkOpen, setLinkOpen] = useState(false);
    const [aiImageOpen, setAiImageOpen] = useState(false);

    const dropInputRef = useRef<HTMLInputElement>(null);

    const uploadForm = useForm<{
        type: FileMediaType;
        name: string;
        file: File | null;
    }>({
        type: 'image',
        name: '',
        file: null,
    });

    const textForm = useForm({
        type: 'text' as const,
        name: '',
        text_content: '',
    });

    const linkForm = useForm({
        type: 'link' as const,
        name: '',
        url: '',
    });

    const renameForm = useForm({ name: '' });
    const editForm = useForm({ name: '', text_content: '', url: '' });
    const replaceForm = useForm<{ name: string; file: File | null }>({
        name: '',
        file: null,
    });

    useEffect(() => {
        setSearchInput(filters.q);
    }, [filters.q]);

    useEffect(() => {
        const handle = window.setTimeout(() => {
            if (searchInput === filters.q) {
                return;
            }

            navigate({ q: searchInput });
        }, 350);

        return () => window.clearTimeout(handle);
        // eslint-disable-next-line react-hooks/exhaustive-deps -- navigate via filters snapshot
    }, [searchInput]);

    function navigate(
        patch: Partial<{
            q: string;
            type: FilterTab;
            sort: MediaSortValue;
            view: 'grid' | 'list';
            page: number;
        }>,
    ) {
        const query = {
            q: patch.q ?? filters.q,
            type: patch.type ?? filters.type,
            sort: patch.sort ?? filters.sort,
            view: patch.view ?? filters.view,
            ...(patch.page ? { page: patch.page } : {}),
        };

        router.get(media.url(), query, {
            preserveState: true,
            replace: true,
            preserveScroll: true,
        });
    }

    function openFileUpload(type: FileMediaType, file?: File | null) {
        setUploadType(type);
        uploadForm.clearErrors();
        uploadForm.setData({
            type,
            name: file ? stripExtension(file.name) : '',
            file: file ?? null,
        });
        setUploadOpen(true);
    }

    function handleDroppedOrPickedFile(
        file: File,
        preferredType?: FileMediaType,
    ) {
        const inferred = preferredType ?? inferMediaType(file);
        if (!inferred) {
            return;
        }

        openFileUpload(inferred, file);
    }

    function onDropzoneDrop(event: DragEvent<HTMLButtonElement>) {
        event.preventDefault();
        setDragOver(false);
        if (!canManage) {
            return;
        }

        const file = event.dataTransfer.files?.[0];
        if (file) {
            handleDroppedOrPickedFile(file);
        }
    }

    function submitUpload(event: FormEvent) {
        event.preventDefault();
        uploadForm.post(mediaRoutes.store.url(), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                uploadForm.reset();
                setUploadOpen(false);
            },
        });
    }

    function submitText(event: FormEvent) {
        event.preventDefault();
        textForm.post(mediaRoutes.store.url(), {
            preserveScroll: true,
            onSuccess: () => {
                textForm.reset();
                setTextOpen(false);
            },
        });
    }

    function submitLink(event: FormEvent) {
        event.preventDefault();
        linkForm.post(mediaRoutes.store.url(), {
            preserveScroll: true,
            onSuccess: () => {
                linkForm.reset();
                setLinkOpen(false);
            },
        });
    }

    function openRename(item: MediaListItem) {
        setRenameItem(item);
        renameForm.clearErrors();
        renameForm.setData('name', item.name);
    }

    function submitRename(event: FormEvent) {
        event.preventDefault();
        if (!renameItem) {
            return;
        }

        renameForm.patch(mediaRoutes.update.url(renameItem.id), {
            preserveScroll: true,
            onSuccess: () => {
                setRenameItem(null);
                renameForm.reset();
            },
        });
    }

    function openEdit(item: MediaListItem) {
        setEditItem(item);
        editForm.clearErrors();
        editForm.setData({
            name: item.name,
            text_content: item.text_content ?? '',
            url: item.url ?? '',
        });
    }

    function submitEdit(event: FormEvent) {
        event.preventDefault();
        if (!editItem) {
            return;
        }

        if (editItem.type === 'text') {
            editForm.transform((data) => ({
                name: data.name,
                text_content: data.text_content,
            }));
        } else {
            editForm.transform((data) => ({
                name: data.name,
                url: data.url,
            }));
        }

        editForm.patch(mediaRoutes.update.url(editItem.id), {
            preserveScroll: true,
            onSuccess: () => {
                setEditItem(null);
                editForm.reset();
            },
        });
    }

    function openReplace(item: MediaListItem) {
        setReplaceItem(item);
        replaceForm.clearErrors();
        replaceForm.setData({ name: item.name, file: null });
    }

    function submitReplace(event: FormEvent) {
        event.preventDefault();
        if (!replaceItem) {
            return;
        }

        replaceForm.post(mediaRoutes.replace.url(replaceItem.id), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                setReplaceItem(null);
                replaceForm.reset();
            },
        });
    }

    function confirmDelete() {
        if (!deleteItem) {
            return;
        }

        router.delete(mediaRoutes.destroy.url(deleteItem.id), {
            preserveScroll: true,
            onSuccess: () => setDeleteItem(null),
        });
    }

    function duplicateItem(item: MediaListItem) {
        router.post(
            mediaRoutes.duplicate.url(item.id),
            {},
            { preserveScroll: true },
        );
    }

    function renderActions(item: MediaListItem): ReactNode {
        return (
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="size-8"
                        aria-label={`Actions for ${item.name}`}
                    >
                        <MoreHorizontal className="size-4" />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" className="w-48">
                    <DropdownMenuItem onSelect={() => setPreviewItem(item)}>
                        <Eye />
                        Preview
                    </DropdownMenuItem>
                    {canManage ? (
                        <DropdownMenuItem onSelect={() => openRename(item)}>
                            <Pencil />
                            Rename
                        </DropdownMenuItem>
                    ) : null}
                    {canManage && item.is_file_based ? (
                        <DropdownMenuItem onSelect={() => openReplace(item)}>
                            <Upload />
                            Replace
                        </DropdownMenuItem>
                    ) : null}
                    {canManage && item.is_editable_content ? (
                        <DropdownMenuItem onSelect={() => openEdit(item)}>
                            <Pencil />
                            Edit
                        </DropdownMenuItem>
                    ) : null}
                    {canManage ? (
                        <DropdownMenuItem onSelect={() => duplicateItem(item)}>
                            <Copy />
                            Duplicate
                        </DropdownMenuItem>
                    ) : null}
                    {item.download_url ? (
                        <DropdownMenuItem asChild>
                            <a href={item.download_url}>
                                <Download />
                                Download
                            </a>
                        </DropdownMenuItem>
                    ) : null}
                    {canDelete ? (
                        <>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem
                                variant="destructive"
                                onSelect={() => setDeleteItem(item)}
                            >
                                <Trash2 />
                                Delete
                            </DropdownMenuItem>
                        </>
                    ) : null}
                </DropdownMenuContent>
            </DropdownMenu>
        );
    }

    const empty = emptyCopy(filters.type);
    const items = paginated.data;
    const isEmpty = items.length === 0;

    return (
        <>
            <Head title="Media Library" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 className="font-display text-2xl font-semibold tracking-tight">
                            Media Library
                        </h1>
                        <p className="text-muted-foreground mt-1 text-sm">
                            {counts.all} files · {formatBytes(total_bytes)} used
                        </p>
                    </div>

                    {canManage ? (
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button data-test="media-add-button">
                                    <Plus />
                                    Add Media
                                    <ChevronDown className="size-3.5 opacity-70" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" className="w-52">
                                <DropdownMenuItem
                                    onSelect={() => openFileUpload('image')}
                                >
                                    <Image />
                                    Upload Image
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    onSelect={() => openFileUpload('video')}
                                >
                                    <Video />
                                    Upload Video
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    onSelect={() => {
                                        textForm.clearErrors();
                                        textForm.reset();
                                        setTextOpen(true);
                                    }}
                                >
                                    <Type />
                                    Add Text
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    onSelect={() => openFileUpload('logo')}
                                >
                                    <Folder />
                                    Upload Logo
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    onSelect={() => openFileUpload('document')}
                                >
                                    <FileText />
                                    Upload Document
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    onSelect={() => {
                                        linkForm.clearErrors();
                                        linkForm.reset();
                                        setLinkOpen(true);
                                    }}
                                >
                                    <Link2 />
                                    Add Link
                                </DropdownMenuItem>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem
                                    data-test="media-generate-ai"
                                    onSelect={() => setAiImageOpen(true)}
                                >
                                    <Sparkles />
                                    Generate with AI
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    ) : null}
                </div>

                {canManage ? (
                    <>
                        <input
                            ref={dropInputRef}
                            type="file"
                            className="sr-only"
                            accept={DROPZONE_ACCEPT}
                            aria-hidden
                            tabIndex={-1}
                            onChange={(event) => {
                                const file = event.target.files?.[0];
                                if (file) {
                                    handleDroppedOrPickedFile(file);
                                }
                                event.target.value = '';
                            }}
                        />
                        <button
                            type="button"
                            onClick={() => dropInputRef.current?.click()}
                            onDragOver={(event) => {
                                event.preventDefault();
                                setDragOver(true);
                            }}
                            onDragLeave={() => setDragOver(false)}
                            onDrop={onDropzoneDrop}
                            onKeyDown={(
                                event: KeyboardEvent<HTMLButtonElement>,
                            ) => {
                                if (
                                    event.key === 'Enter' ||
                                    event.key === ' '
                                ) {
                                    event.preventDefault();
                                    dropInputRef.current?.click();
                                }
                            }}
                            className={cn(
                                'border-border hover:border-primary/40 focus-visible:ring-ring w-full rounded-xl border-2 border-dashed py-8 text-center transition-colors focus-visible:ring-2 focus-visible:outline-none',
                                dragOver && 'border-primary bg-primary/5',
                            )}
                            aria-label="Drop files here to upload, or press Enter to browse"
                        >
                            <Upload
                                className={cn(
                                    'text-muted-foreground mx-auto mb-2 size-8',
                                    dragOver && 'text-primary',
                                )}
                                aria-hidden
                            />
                            <p className="text-foreground text-sm font-medium">
                                Drop files here to upload
                            </p>
                            <p className="text-muted-foreground mt-0.5 text-xs">
                                Images, videos, logos, and documents · up to
                                500MB for video
                            </p>
                        </button>
                    </>
                ) : null}

                <div className="flex flex-col gap-3 lg:flex-row lg:items-center">
                    <div
                        className="flex flex-wrap gap-1.5"
                        role="tablist"
                        aria-label="Media type"
                    >
                        {TYPE_TABS.map((tab) => {
                            const active = filters.type === tab.id;
                            return (
                                <button
                                    key={tab.id}
                                    type="button"
                                    role="tab"
                                    aria-selected={active}
                                    data-test={`media-tab-${tab.id}`}
                                    onClick={() => navigate({ type: tab.id })}
                                    className={cn(
                                        'inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-medium transition-colors',
                                        active
                                            ? 'bg-primary text-primary-foreground'
                                            : 'bg-secondary text-muted-foreground hover:text-foreground',
                                    )}
                                >
                                    {tab.label}
                                    <span
                                        className={cn(
                                            'font-mono tabular-nums',
                                            active
                                                ? 'text-primary-foreground/80'
                                                : 'text-muted-foreground',
                                        )}
                                    >
                                        {counts[tab.countKey]}
                                    </span>
                                </button>
                            );
                        })}
                    </div>

                    <div className="flex flex-wrap items-center gap-2 lg:ml-auto">
                        <form
                            className="relative"
                            onSubmit={(event) => {
                                event.preventDefault();
                                navigate({ q: searchInput });
                            }}
                        >
                            <Search
                                className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2"
                                aria-hidden
                            />
                            <Input
                                value={searchInput}
                                onChange={(event) =>
                                    setSearchInput(event.target.value)
                                }
                                placeholder="Search files…"
                                className="w-48 pl-9 sm:w-56"
                                aria-label="Search media"
                                data-test="media-search"
                            />
                        </form>

                        <div className="grid gap-1">
                            <Label htmlFor="media-sort" className="sr-only">
                                Sort
                            </Label>
                            <select
                                id="media-sort"
                                className={cn(
                                    selectClassName,
                                    'w-auto min-w-32',
                                )}
                                value={filters.sort}
                                onChange={(event) =>
                                    navigate({
                                        sort: event.target
                                            .value as MediaSortValue,
                                    })
                                }
                                aria-label="Sort media"
                            >
                                {SORT_OPTIONS.map((option) => (
                                    <option
                                        key={option.value}
                                        value={option.value}
                                    >
                                        {option.label}
                                    </option>
                                ))}
                            </select>
                        </div>

                        <div
                            className="bg-secondary flex gap-1 rounded-lg p-1"
                            role="group"
                            aria-label="View mode"
                        >
                            <Button
                                type="button"
                                size="icon"
                                variant={
                                    filters.view === 'grid'
                                        ? 'secondary'
                                        : 'ghost'
                                }
                                className={cn(
                                    'size-8',
                                    filters.view === 'grid' &&
                                        'bg-card shadow-xs',
                                )}
                                aria-pressed={filters.view === 'grid'}
                                aria-label="Grid view"
                                onClick={() => navigate({ view: 'grid' })}
                            >
                                <Grid3X3 className="size-4" />
                            </Button>
                            <Button
                                type="button"
                                size="icon"
                                variant={
                                    filters.view === 'list'
                                        ? 'secondary'
                                        : 'ghost'
                                }
                                className={cn(
                                    'size-8',
                                    filters.view === 'list' &&
                                        'bg-card shadow-xs',
                                )}
                                aria-pressed={filters.view === 'list'}
                                aria-label="List view"
                                onClick={() => navigate({ view: 'list' })}
                            >
                                <List className="size-4" />
                            </Button>
                        </div>
                    </div>
                </div>

                {isEmpty ? (
                    <EmptyState
                        icon={Folder}
                        title={empty.title}
                        description={empty.description}
                        action={
                            canManage ? (
                                <Button
                                    type="button"
                                    data-test="media-empty-add-button"
                                    onClick={() => {
                                        if (filters.type === 'text') {
                                            setTextOpen(true);
                                            return;
                                        }
                                        if (filters.type === 'link') {
                                            setLinkOpen(true);
                                            return;
                                        }
                                        if (
                                            filters.type === 'image' ||
                                            filters.type === 'video' ||
                                            filters.type === 'logo' ||
                                            filters.type === 'document'
                                        ) {
                                            openFileUpload(filters.type);
                                            return;
                                        }
                                        openFileUpload('image');
                                    }}
                                >
                                    <Plus />
                                    Add Media
                                </Button>
                            ) : undefined
                        }
                    />
                ) : filters.view === 'grid' ? (
                    <div
                        className="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6"
                        data-test="media-grid"
                    >
                        {items.map((item) => (
                            <div
                                key={item.id}
                                data-test={`media-card-${item.id}`}
                                className="group border-border bg-card hover:border-primary/30 overflow-hidden rounded-xl border transition-all hover:shadow-lg"
                            >
                                <button
                                    type="button"
                                    className="w-full text-left focus-visible:outline-none"
                                    onClick={() => setPreviewItem(item)}
                                    aria-label={`Preview ${item.name}`}
                                >
                                    <div className="relative">
                                        <CardPreview item={item} />
                                        <div className="absolute top-2 left-2">
                                            <span
                                                className={cn(
                                                    'inline-flex items-center gap-1 rounded-full px-1.5 py-0.5 font-mono text-[9px] font-medium uppercase',
                                                    typeBadgeClass(item.type),
                                                )}
                                            >
                                                {typeIcon(item.type, 'size-3')}
                                                {item.type}
                                            </span>
                                        </div>
                                    </div>
                                </button>
                                <div className="border-border flex items-start justify-between gap-1 border-t p-2.5">
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate text-xs font-medium">
                                            {item.name}
                                        </p>
                                        <p className="text-muted-foreground font-mono text-[10px]">
                                            {item.is_file_based
                                                ? formatBytes(item.size_bytes)
                                                : item.type_label}
                                        </p>
                                    </div>
                                    {renderActions(item)}
                                </div>
                            </div>
                        ))}
                    </div>
                ) : (
                    <div
                        className="border-border bg-card overflow-hidden rounded-xl border"
                        data-test="media-list"
                    >
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>File</TableHead>
                                    <TableHead className="hidden sm:table-cell">
                                        Type
                                    </TableHead>
                                    <TableHead className="hidden md:table-cell">
                                        Size
                                    </TableHead>
                                    <TableHead className="hidden lg:table-cell">
                                        Resolution
                                    </TableHead>
                                    <TableHead className="hidden lg:table-cell">
                                        Added
                                    </TableHead>
                                    <TableHead className="w-12">
                                        <span className="sr-only">Actions</span>
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {items.map((item) => (
                                    <TableRow
                                        key={item.id}
                                        data-test={`media-card-${item.id}`}
                                    >
                                        <TableCell>
                                            <button
                                                type="button"
                                                className="flex items-center gap-3 text-left"
                                                onClick={() =>
                                                    setPreviewItem(item)
                                                }
                                            >
                                                <div className="bg-muted flex size-10 shrink-0 items-center justify-center overflow-hidden rounded-lg">
                                                    {item.type === 'text' ? (
                                                        <Type className="size-4 text-violet-500" />
                                                    ) : item.type === 'link' ? (
                                                        <Link2 className="text-primary size-4" />
                                                    ) : item.type ===
                                                      'document' ? (
                                                        <FileText className="text-muted-foreground size-4" />
                                                    ) : item.preview_url &&
                                                      (item.type === 'image' ||
                                                          item.type ===
                                                              'logo') ? (
                                                        <img
                                                            src={
                                                                item.preview_url
                                                            }
                                                            alt=""
                                                            className="size-full object-cover"
                                                        />
                                                    ) : (
                                                        typeIcon(item.type)
                                                    )}
                                                </div>
                                                <span className="text-sm font-medium">
                                                    {item.name}
                                                </span>
                                            </button>
                                        </TableCell>
                                        <TableCell className="text-muted-foreground hidden font-mono text-xs capitalize sm:table-cell">
                                            {item.type_label}
                                        </TableCell>
                                        <TableCell className="text-muted-foreground hidden font-mono text-sm md:table-cell">
                                            {formatBytes(item.size_bytes)}
                                        </TableCell>
                                        <TableCell className="text-muted-foreground hidden font-mono text-sm lg:table-cell">
                                            {item.dimensions ?? '—'}
                                        </TableCell>
                                        <TableCell className="text-muted-foreground hidden font-mono text-sm lg:table-cell">
                                            {formatRelativeDate(
                                                item.created_at,
                                            )}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            {renderActions(item)}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                )}

                {paginated.meta.last_page > 1 ? (
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <p className="text-muted-foreground text-sm">
                            Showing {paginated.meta.from ?? 0}–
                            {paginated.meta.to ?? 0} of {paginated.meta.total}
                        </p>
                        <div className="flex gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                disabled={!paginated.links.prev}
                                onClick={() =>
                                    navigate({
                                        page: paginated.meta.current_page - 1,
                                    })
                                }
                            >
                                Previous
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                disabled={!paginated.links.next}
                                onClick={() =>
                                    navigate({
                                        page: paginated.meta.current_page + 1,
                                    })
                                }
                            >
                                Next
                            </Button>
                        </div>
                    </div>
                ) : null}
            </div>

            {/* Preview */}
            <Dialog
                open={previewItem !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setPreviewItem(null);
                    }
                }}
            >
                <DialogContent className="max-w-3xl" data-test="media-preview">
                    <DialogHeader>
                        <DialogTitle>{previewItem?.name}</DialogTitle>
                        <DialogDescription>
                            {previewItem
                                ? `${previewItem.type_label}${previewItem.dimensions ? ` · ${previewItem.dimensions}` : ''}${previewItem.size_bytes != null ? ` · ${formatBytes(previewItem.size_bytes)}` : ''}`
                                : ''}
                        </DialogDescription>
                    </DialogHeader>
                    {previewItem ? <MediaPreview item={previewItem} /> : null}
                </DialogContent>
            </Dialog>

            {/* File upload */}
            <Dialog
                open={uploadOpen}
                onOpenChange={(open) => {
                    setUploadOpen(open);
                    if (!open) {
                        uploadForm.reset();
                        uploadForm.clearErrors();
                    }
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            Upload{' '}
                            {uploadType.charAt(0).toUpperCase() +
                                uploadType.slice(1)}
                        </DialogTitle>
                        <DialogDescription>
                            Choose a file and optionally set a display name.
                        </DialogDescription>
                    </DialogHeader>
                    <form onSubmit={submitUpload} className="space-y-4">
                        <div className="grid gap-2">
                            <Label htmlFor="media-upload-name">Name</Label>
                            <Input
                                id="media-upload-name"
                                data-test="media-upload-name"
                                value={uploadForm.data.name}
                                onChange={(event) =>
                                    uploadForm.setData(
                                        'name',
                                        event.target.value,
                                    )
                                }
                                required
                                placeholder="Display name"
                            />
                            <InputError message={uploadForm.errors.name} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="media-upload-file">File</Label>
                            <Input
                                id="media-upload-file"
                                data-test="media-upload-file"
                                type="file"
                                accept={FILE_ACCEPT[uploadType]}
                                onChange={(event) => {
                                    const file =
                                        event.target.files?.[0] ?? null;
                                    uploadForm.setData('file', file);
                                    if (file && !uploadForm.data.name) {
                                        uploadForm.setData(
                                            'name',
                                            stripExtension(file.name),
                                        );
                                    }
                                }}
                                required={!uploadForm.data.file}
                            />
                            {uploadForm.data.file ? (
                                <p className="text-muted-foreground text-xs">
                                    Selected: {uploadForm.data.file.name}
                                </p>
                            ) : null}
                            <InputError message={uploadForm.errors.file} />
                        </div>
                        <UploadProgress progress={uploadForm.progress} />
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setUploadOpen(false)}
                            >
                                Cancel
                            </Button>
                            <Button
                                type="submit"
                                disabled={uploadForm.processing}
                                data-test="media-upload-submit"
                            >
                                {uploadForm.processing ? <Spinner /> : null}
                                Upload
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Add / edit text */}
            <Dialog
                open={textOpen}
                onOpenChange={(open) => {
                    setTextOpen(open);
                    if (!open) {
                        textForm.reset();
                        textForm.clearErrors();
                    }
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Add Text</DialogTitle>
                        <DialogDescription>
                            Save reusable text content for your signage.
                        </DialogDescription>
                    </DialogHeader>
                    <form onSubmit={submitText} className="space-y-4">
                        <div className="grid gap-2">
                            <Label htmlFor="media-text-name">Name</Label>
                            <Input
                                id="media-text-name"
                                value={textForm.data.name}
                                onChange={(event) =>
                                    textForm.setData('name', event.target.value)
                                }
                                required
                                placeholder="e.g. Welcome Message"
                                data-test="media-text-name"
                            />
                            <InputError message={textForm.errors.name} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="media-text-content">
                                Text content
                            </Label>
                            <textarea
                                id="media-text-content"
                                className={textareaClassName}
                                value={textForm.data.text_content}
                                onChange={(event) =>
                                    textForm.setData(
                                        'text_content',
                                        event.target.value,
                                    )
                                }
                                required
                                rows={5}
                                placeholder="Enter your text content…"
                                data-test="media-text-content"
                            />
                            <InputError
                                message={textForm.errors.text_content}
                            />
                        </div>
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setTextOpen(false)}
                            >
                                Cancel
                            </Button>
                            <Button
                                type="submit"
                                disabled={textForm.processing}
                                data-test="media-upload-submit"
                            >
                                {textForm.processing ? <Spinner /> : null}
                                Save Text
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Add link */}
            <Dialog
                open={linkOpen}
                onOpenChange={(open) => {
                    setLinkOpen(open);
                    if (!open) {
                        linkForm.reset();
                        linkForm.clearErrors();
                    }
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Add Link</DialogTitle>
                        <DialogDescription>
                            Save a website or stream URL as media.
                        </DialogDescription>
                    </DialogHeader>
                    <form onSubmit={submitLink} className="space-y-4">
                        <div className="grid gap-2">
                            <Label htmlFor="media-link-name">Name</Label>
                            <Input
                                id="media-link-name"
                                value={linkForm.data.name}
                                onChange={(event) =>
                                    linkForm.setData('name', event.target.value)
                                }
                                required
                                placeholder="Display name"
                                data-test="media-link-name"
                            />
                            <InputError message={linkForm.errors.name} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="media-link-url">URL</Label>
                            <Input
                                id="media-link-url"
                                type="url"
                                value={linkForm.data.url}
                                onChange={(event) =>
                                    linkForm.setData('url', event.target.value)
                                }
                                required
                                placeholder="https://"
                                data-test="media-link-url"
                            />
                            <InputError message={linkForm.errors.url} />
                        </div>
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setLinkOpen(false)}
                            >
                                Cancel
                            </Button>
                            <Button
                                type="submit"
                                disabled={linkForm.processing}
                                data-test="media-upload-submit"
                            >
                                {linkForm.processing ? <Spinner /> : null}
                                Save Link
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Rename */}
            <Dialog
                open={renameItem !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setRenameItem(null);
                        renameForm.reset();
                    }
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Rename media</DialogTitle>
                        <DialogDescription>
                            Update the display name for this item.
                        </DialogDescription>
                    </DialogHeader>
                    <form onSubmit={submitRename} className="space-y-4">
                        <div className="grid gap-2">
                            <Label htmlFor="media-rename-input">Name</Label>
                            <Input
                                id="media-rename-input"
                                value={renameForm.data.name}
                                onChange={(event) =>
                                    renameForm.setData(
                                        'name',
                                        event.target.value,
                                    )
                                }
                                required
                                data-test="media-rename-input"
                            />
                            <InputError message={renameForm.errors.name} />
                        </div>
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setRenameItem(null)}
                            >
                                Cancel
                            </Button>
                            <Button
                                type="submit"
                                disabled={renameForm.processing}
                            >
                                {renameForm.processing ? <Spinner /> : null}
                                Save
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Edit text / link */}
            <Dialog
                open={editItem !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setEditItem(null);
                        editForm.reset();
                    }
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            Edit {editItem?.type === 'link' ? 'link' : 'text'}
                        </DialogTitle>
                        <DialogDescription>
                            Update the content for this media item.
                        </DialogDescription>
                    </DialogHeader>
                    <form onSubmit={submitEdit} className="space-y-4">
                        <div className="grid gap-2">
                            <Label htmlFor="media-edit-name">Name</Label>
                            <Input
                                id="media-edit-name"
                                value={editForm.data.name}
                                onChange={(event) =>
                                    editForm.setData('name', event.target.value)
                                }
                                required
                                data-test={
                                    editItem?.type === 'link'
                                        ? 'media-link-name'
                                        : 'media-text-name'
                                }
                            />
                            <InputError message={editForm.errors.name} />
                        </div>
                        {editItem?.type === 'text' ? (
                            <div className="grid gap-2">
                                <Label htmlFor="media-edit-text">
                                    Text content
                                </Label>
                                <textarea
                                    id="media-edit-text"
                                    className={textareaClassName}
                                    value={editForm.data.text_content}
                                    onChange={(event) =>
                                        editForm.setData(
                                            'text_content',
                                            event.target.value,
                                        )
                                    }
                                    required
                                    rows={5}
                                    data-test="media-text-content"
                                />
                                <InputError
                                    message={editForm.errors.text_content}
                                />
                            </div>
                        ) : (
                            <div className="grid gap-2">
                                <Label htmlFor="media-edit-url">URL</Label>
                                <Input
                                    id="media-edit-url"
                                    type="url"
                                    value={editForm.data.url}
                                    onChange={(event) =>
                                        editForm.setData(
                                            'url',
                                            event.target.value,
                                        )
                                    }
                                    required
                                    data-test="media-link-url"
                                />
                                <InputError message={editForm.errors.url} />
                            </div>
                        )}
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setEditItem(null)}
                            >
                                Cancel
                            </Button>
                            <Button
                                type="submit"
                                disabled={editForm.processing}
                            >
                                {editForm.processing ? <Spinner /> : null}
                                Save changes
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Replace file */}
            <Dialog
                open={replaceItem !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setReplaceItem(null);
                        replaceForm.reset();
                    }
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Replace file</DialogTitle>
                        <DialogDescription>
                            Upload a new file for “{replaceItem?.name}”. Type
                            stays the same.
                        </DialogDescription>
                    </DialogHeader>
                    <form onSubmit={submitReplace} className="space-y-4">
                        <div className="grid gap-2">
                            <Label htmlFor="media-replace-name">
                                Name (optional)
                            </Label>
                            <Input
                                id="media-replace-name"
                                value={replaceForm.data.name}
                                onChange={(event) =>
                                    replaceForm.setData(
                                        'name',
                                        event.target.value,
                                    )
                                }
                                placeholder="Keep current name"
                            />
                            <InputError message={replaceForm.errors.name} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="media-replace-file">New file</Label>
                            <Input
                                id="media-replace-file"
                                data-test="media-upload-file"
                                type="file"
                                accept={
                                    replaceItem &&
                                    (replaceItem.type === 'image' ||
                                        replaceItem.type === 'video' ||
                                        replaceItem.type === 'logo' ||
                                        replaceItem.type === 'document')
                                        ? FILE_ACCEPT[replaceItem.type]
                                        : undefined
                                }
                                onChange={(event) =>
                                    replaceForm.setData(
                                        'file',
                                        event.target.files?.[0] ?? null,
                                    )
                                }
                                required
                            />
                            <InputError message={replaceForm.errors.file} />
                        </div>
                        <UploadProgress progress={replaceForm.progress} />
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setReplaceItem(null)}
                            >
                                Cancel
                            </Button>
                            <Button
                                type="submit"
                                disabled={replaceForm.processing}
                                data-test="media-upload-submit"
                            >
                                {replaceForm.processing ? <Spinner /> : null}
                                Replace
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            {/* Delete confirm */}
            <Dialog
                open={deleteItem !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setDeleteItem(null);
                    }
                }}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Delete media</DialogTitle>
                        <DialogDescription>
                            Delete “{deleteItem?.name}”? This permanently
                            removes the file from the workspace library and
                            cannot be undone.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setDeleteItem(null)}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="button"
                            variant="destructive"
                            data-test="media-delete-confirm"
                            onClick={confirmDelete}
                        >
                            Delete
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <AiImageDialog
                open={aiImageOpen}
                onOpenChange={setAiImageOpen}
                available={ai?.available ?? false}
                unavailableMessage={ai?.message}
                onSaved={() => {
                    router.reload({ only: ['media', 'counts', 'total_bytes'] });
                }}
            />
        </>
    );
}

MediaLibrary.layout = () => ({
    breadcrumbs: [{ title: 'Media', href: media.url() }],
});
