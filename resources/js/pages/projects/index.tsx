import { Form, Head, Link } from '@inertiajs/react';
import { FolderPlus } from 'lucide-react';
import { useState } from 'react';
import { EmptyState, PageHeader } from '@/components/content/page-header';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useTeamSlug } from '@/hooks/use-team';
import { timeAgo } from '@/lib/format';
import projectsRoutes from '@/routes/projects';
import type { ProjectSummary } from '@/types';

export default function ProjectsIndex({
    projects,
}: {
    projects: ProjectSummary[];
}) {
    const team = useTeamSlug();

    return (
        <>
            <Head title="Projects" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Projects"
                    description="A project bundles context and instructions. Chats started from it get both automatically."
                    actions={<NewProjectDialog />}
                />

                {projects.length === 0 ? (
                    <EmptyState
                        title="No projects yet"
                        description="Create one for a campaign, a client or a product line, then include the context it needs."
                        action={<NewProjectDialog />}
                    />
                ) : (
                    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        {projects.map((project) => (
                            <Link
                                key={project.id}
                                href={projectsRoutes.show([team, project.id])}
                            >
                                <Card className="h-full py-4 transition-colors hover:bg-accent/50">
                                    <CardHeader className="px-4">
                                        <CardTitle className="truncate">
                                            {project.name}
                                        </CardTitle>
                                        <CardDescription className="line-clamp-2">
                                            {project.description ||
                                                'No description'}
                                        </CardDescription>
                                        <p className="pt-2 text-xs text-muted-foreground">
                                            {project.contextItemsCount} context
                                            items · {project.chatsCount} chats
                                            {project.hasInstructions &&
                                                ' · has instructions'}
                                            {' · '}
                                            {timeAgo(project.updatedAt)}
                                        </p>
                                    </CardHeader>
                                </Card>
                            </Link>
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}

function NewProjectDialog() {
    const team = useTeamSlug();
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button>
                    <FolderPlus /> New project
                </Button>
            </DialogTrigger>
            <DialogContent>
                <Form
                    key={String(open)}
                    {...projectsRoutes.store.form(team)}
                    className="space-y-6"
                >
                    {({ errors, processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>New project</DialogTitle>
                            </DialogHeader>
                            <div className="grid gap-2">
                                <Label htmlFor="name">Name</Label>
                                <Input
                                    id="name"
                                    name="name"
                                    required
                                    placeholder="Spring launch"
                                />
                                <InputError message={errors.name} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="description">
                                    Description (optional)
                                </Label>
                                <Textarea
                                    id="description"
                                    name="description"
                                    rows={3}
                                />
                            </div>
                            <DialogFooter>
                                <Button type="submit" disabled={processing}>
                                    Create project
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
