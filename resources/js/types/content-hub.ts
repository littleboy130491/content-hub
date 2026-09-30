export type ContextType = 'text' | 'image' | 'video' | 'web';
export type PromptMode = 'append' | 'replace';
export type OutputType = 'text' | 'image' | 'video';
export type OutputStatus =
    | 'awaiting_confirmation'
    | 'pending'
    | 'processing'
    | 'completed'
    | 'failed';
export type ScrapeStatus = 'pending' | 'processing' | 'completed' | 'failed';

export type MediaFile = {
    id: number;
    name: string;
    mime: string | null;
    size: number;
    url: string;
};

export type ContextItem = {
    id: number;
    type: ContextType;
    typeLabel: string;
    title: string;
    excerpt: string;
    body?: string | null;
    description?: string | null;
    descriptionModel?: string | null;
    sourceUrl: string | null;
    fetchedAt: string | null;
    untrusted: boolean;
    file: MediaFile | null;
    author: string | null;
    projectsCount?: number;
    createdAt: string;
    updatedAt: string;
};

export type ProjectSummary = {
    id: number;
    name: string;
    description: string | null;
    hasInstructions: boolean;
    contextItemsCount: number;
    chatsCount: number;
    updatedAt: string;
};

export type Chat = {
    id: number;
    title: string;
    project: { id: number; name: string } | null;
    systemPrompt: string | null;
    promptMode: PromptMode;
    models: {
        text: string | null;
        image: string | null;
        video: string | null;
        media: string | null;
    };
    excludedContextItemIds: number[];
    archived: boolean;
    author: string | null;
    messagesCount?: number;
    updatedAt: string;
};

export type LibraryOption = {
    id: number;
    type: ContextType;
    typeLabel: string;
    title: string;
    untrusted: boolean;
};

export type Attachment = MediaFile & { described: boolean };

export type ChatMessage = {
    id: number;
    role: 'user' | 'assistant';
    content: string;
    author: string | null;
    model: string | null;
    notes: string[];
    failed: boolean;
    savedOutputId: number | null;
    attachments: Attachment[];
    createdAt: string;
};

export type Output = {
    id: number;
    type: OutputType;
    title: string;
    prompt: string | null;
    content: string | null;
    model: string | null;
    status: OutputStatus;
    error: string | null;
    options: Record<string, unknown> | null;
    cost: number | null;
    file: MediaFile | null;
    chatId: number | null;
    chatTitle: string | null;
    messageId: number | null;
    contextItemId: number | null;
    author: string | null;
    createdAt: string;
};

export type WebScrape = {
    id: number;
    url: string;
    instruction: string | null;
    mode: 'page' | 'crawl' | null;
    modeReason: string | null;
    status: ScrapeStatus;
    title: string | null;
    markdown: string | null;
    pages: { url: string; title: string | null }[];
    credits: number;
    error: string | null;
    chat: { id: number; title: string } | null;
    attachedToChat: boolean;
    contextItemId: number | null;
    createdAt: string;
};

export type ContextEntry = {
    key: string;
    title: string;
    type: ContextType;
    source: 'project' | 'chat' | 'attachment' | 'scrape';
    handling: 'text' | 'image' | 'convert' | 'excluded' | 'unsupported';
    untrusted: boolean;
    cached: boolean;
    contextItemId: number | null;
    mediaId: number | null;
    scrapeId: number | null;
};

export type ModelOption = {
    id: string;
    name: string;
    input: string[];
    output: string[];
    contextLength: number | null;
    supportsTools: boolean;
};

export type ModelKind = 'text' | 'image' | 'video' | 'media';

export type PromptLayer = {
    source: 'settings' | 'project' | 'chat';
    text: string;
    mode: PromptMode;
    active: boolean;
};

export type UsageSummary = {
    spent: number;
    limit: number;
    scrapeCredits: number;
    scrapeLimit: number;
};

export type Paginated<T> = {
    data: T[];
    links: {
        first: string;
        last: string;
        prev: string | null;
        next: string | null;
    };
    meta: {
        current_page: number;
        last_page: number;
        total: number;
        per_page: number;
    };
};
