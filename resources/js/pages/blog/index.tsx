import { Head, Link } from '@inertiajs/react';
import { ArrowRight, BookOpen, Clock3, MessageSquareText, Sparkles } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { show as blogArticle } from '@/routes/blog';

type BlogPost = {
    title: string;
    slug: string;
    category: string | null;
    excerpt: string | null;
    published_at: string;
    meta_title: string | null;
};

function formatDate(date: string): string {
    return new Date(date).toLocaleDateString('en-GH', { dateStyle: 'medium' });
}

export default function BlogIndex({ posts }: { posts: BlogPost[] }) {
    const [featuredPost, ...morePosts] = posts;

    return (
        <>
            <Head title="Blog | Omnicast">
                <meta
                    head-key="description"
                    name="description"
                    content="Practical guides to SMS marketing, customer communication and messaging technology for businesses in Ghana."
                />
                <meta head-key="og:title" property="og:title" content="Omnicast Blog" />
                <meta
                    head-key="og:description"
                    property="og:description"
                    content="Ideas and practical guides for better customer communication."
                />
            </Head>

            <div className="space-y-12 py-8 sm:space-y-16 sm:py-12">
                <section className="relative isolate overflow-hidden rounded-[2rem] border border-border/70 bg-card/75 px-6 py-9 shadow-sm sm:px-10 sm:py-12 lg:px-14 lg:py-14">
                    <div aria-hidden="true" className="pointer-events-none absolute inset-0 -z-10 mesh-backdrop opacity-50" />
                    <div aria-hidden="true" className="pointer-events-none absolute -top-24 -right-14 -z-10 size-72 rounded-full bg-brand/10 blur-3xl" />
                    <div className="grid items-end gap-8 lg:grid-cols-[minmax(0,1fr)_260px]">
                        <div className="max-w-3xl">
                            <span className="inline-flex items-center gap-2 rounded-full bg-brand-soft px-3 py-1 text-xs font-semibold text-accent-foreground ring-1 ring-brand/20">
                                <BookOpen className="size-3.5" />
                                The Omnicast journal
                            </span>
                            <h1 className="mt-6 text-4xl leading-[1.06] font-semibold tracking-tight text-balance sm:text-5xl lg:text-6xl">
                                Better conversations begin with a good idea.
                            </h1>
                            <p className="mt-5 max-w-2xl text-base leading-7 text-muted-foreground sm:text-lg sm:leading-8">
                                Thoughtful, practical notes on reaching people, choosing the right channel and making every message useful.
                            </p>
                        </div>

                        <div className="hidden rounded-2xl border border-border/60 bg-background/75 p-5 shadow-sm lg:block">
                            <span className="flex size-10 items-center justify-center rounded-xl brand-gradient-soft text-brand ring-1 ring-brand/15">
                                <Sparkles className="size-5" />
                            </span>
                            <p className="mt-4 text-sm font-semibold">Made for real work</p>
                            <p className="mt-1 text-sm leading-6 text-muted-foreground">
                                Clear advice for businesses and organisations communicating across Ghana.
                            </p>
                            <p className="mt-4 border-t border-border/60 pt-3 text-xs text-muted-foreground">
                                {posts.length} {posts.length === 1 ? 'article' : 'articles'} to explore
                            </p>
                        </div>
                    </div>
                </section>

                {featuredPost ? (
                    <section className="space-y-5">
                        <div className="flex items-end justify-between gap-4">
                            <div>
                                <p className="text-xs font-semibold tracking-[0.16em] text-brand uppercase">Start here</p>
                                <h2 className="mt-1 text-xl font-semibold tracking-tight sm:text-2xl">Featured article</h2>
                            </div>
                            <span className="hidden text-sm text-muted-foreground sm:inline">
                                {posts.length} {posts.length === 1 ? 'article' : 'articles'}
                            </span>
                        </div>

                        <article className="group relative isolate overflow-hidden rounded-[1.75rem] border border-border/70 bg-card shadow-sm transition-all duration-300 hover:-translate-y-0.5 hover:border-brand/40 hover:shadow-xl hover:shadow-brand/5">
                            <div className="grid min-h-[340px] lg:grid-cols-[minmax(0,1.1fr)_minmax(280px,0.9fr)]">
                                <div className="flex flex-col items-start p-6 sm:p-9 lg:p-11">
                                    <span className="rounded-full bg-brand-soft px-3 py-1 text-xs font-semibold text-accent-foreground ring-1 ring-brand/20">
                                        {featuredPost.category || 'Omnicast insights'}
                                    </span>
                                    <h3 className="mt-5 max-w-2xl text-2xl leading-tight font-semibold tracking-tight text-balance sm:text-3xl lg:text-4xl">
                                        <Link href={blogArticle(featuredPost.slug)} className="focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-brand">
                                            {featuredPost.title}
                                        </Link>
                                    </h3>
                                    <p className="mt-4 max-w-xl text-sm leading-6 text-muted-foreground sm:text-base sm:leading-7">
                                        {featuredPost.excerpt || 'Explore practical ideas and guidance from the Omnicast team.'}
                                    </p>
                                    <div className="mt-auto flex w-full flex-wrap items-center justify-between gap-4 pt-8">
                                        <span className="inline-flex items-center gap-2 text-xs text-muted-foreground">
                                            <Clock3 className="size-3.5" />
                                            {formatDate(featuredPost.published_at)}
                                        </span>
                                        <Button variant="ghost" size="sm" asChild className="group/button">
                                            <Link href={blogArticle(featuredPost.slug)}>
                                                Read article
                                                <ArrowRight className="transition-transform group-hover/button:translate-x-0.5" />
                                            </Link>
                                        </Button>
                                    </div>
                                </div>

                                <Link
                                    href={blogArticle(featuredPost.slug)}
                                    aria-label={`Read ${featuredPost.title}`}
                                    className="relative min-h-56 overflow-hidden border-t border-border/60 bg-brand-soft/50 lg:min-h-full lg:border-t-0 lg:border-l"
                                >
                                    <div aria-hidden="true" className="absolute inset-0 grid-pattern opacity-30 [mask-image:radial-gradient(ellipse_at_center,black,transparent_75%)]" />
                                    <div aria-hidden="true" className="absolute -top-14 -right-10 size-64 rounded-full bg-brand/20 blur-3xl transition-transform duration-700 group-hover:scale-110" />
                                    <div aria-hidden="true" className="absolute -bottom-20 -left-8 size-64 rounded-full bg-signal/15 blur-3xl" />
                                    <div className="absolute inset-0 flex items-center justify-center p-8">
                                        <div className="relative flex aspect-square w-full max-w-[250px] rotate-[-5deg] items-center justify-center rounded-[2rem] border border-white/60 bg-background/75 shadow-2xl shadow-brand/10 backdrop-blur-sm transition-transform duration-500 group-hover:rotate-0 group-hover:scale-[1.03]">
                                            <div className="absolute inset-3 rounded-[1.4rem] border border-brand/10" />
                                            <span className="flex size-24 items-center justify-center rounded-[1.75rem] brand-gradient text-primary-foreground shadow-lg shadow-brand/25">
                                                <MessageSquareText className="size-11" strokeWidth={1.6} />
                                            </span>
                                            <span className="absolute right-5 bottom-5 rounded-full border border-border/70 bg-background/90 px-3 py-1.5 text-[10px] font-semibold tracking-[0.14em] text-muted-foreground uppercase">
                                                Omnicast guide
                                            </span>
                                        </div>
                                    </div>
                                </Link>
                            </div>
                        </article>
                    </section>
                ) : (
                    <section className="rounded-2xl border border-dashed border-border bg-card/50 px-6 py-14 text-center">
                        <span className="mx-auto flex size-12 items-center justify-center rounded-2xl bg-brand-soft text-brand">
                            <BookOpen className="size-5" />
                        </span>
                        <h2 className="mt-4 text-lg font-semibold">New stories are on the way</h2>
                        <p className="mx-auto mt-2 max-w-md text-sm leading-6 text-muted-foreground">
                            We’re preparing practical ideas and guides for better customer communication. Please check back soon.
                        </p>
                    </section>
                )}

                {morePosts.length > 0 && (
                    <section className="space-y-5">
                        <div className="flex items-end justify-between gap-4 border-b border-border/60 pb-4">
                            <div>
                                <p className="text-xs font-semibold tracking-[0.16em] text-brand uppercase">Keep exploring</p>
                                <h2 className="mt-1 text-xl font-semibold tracking-tight sm:text-2xl">More from the blog</h2>
                            </div>
                            <span className="text-sm text-muted-foreground">{morePosts.length} more</span>
                        </div>

                        <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                            {morePosts.map((post, index) => (
                                <article
                                    key={post.slug}
                                    className="group flex min-h-64 flex-col rounded-2xl border border-border/70 bg-card/70 p-6 transition-all duration-300 hover:-translate-y-0.5 hover:border-brand/35 hover:bg-card hover:shadow-lg hover:shadow-brand/5"
                                >
                                    <div className="flex items-center justify-between gap-3">
                                        <span className="rounded-full bg-muted px-2.5 py-1 text-[11px] font-semibold text-muted-foreground">
                                            {post.category || 'Omnicast insights'}
                                        </span>
                                        <span className="text-xs tabular-nums text-muted-foreground/70">{String(index + 2).padStart(2, '0')}</span>
                                    </div>
                                    <h3 className="mt-5 text-xl leading-snug font-semibold tracking-tight text-balance">
                                        <Link href={blogArticle(post.slug)} className="transition-colors group-hover:text-brand focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-brand">
                                            {post.title}
                                        </Link>
                                    </h3>
                                    <p className="mt-3 line-clamp-3 text-sm leading-6 text-muted-foreground">
                                        {post.excerpt || 'Practical ideas and guidance from the Omnicast team.'}
                                    </p>
                                    <div className="mt-auto flex items-center justify-between gap-3 pt-6">
                                        <span className="inline-flex items-center gap-1.5 text-xs text-muted-foreground">
                                            <Clock3 className="size-3.5" />
                                            {formatDate(post.published_at)}
                                        </span>
                                        <Link href={blogArticle(post.slug)} aria-label={`Read ${post.title}`} className="flex size-9 items-center justify-center rounded-full border border-border/70 transition-all group-hover:border-brand/30 group-hover:bg-brand-soft group-hover:text-brand">
                                            <ArrowRight className="size-4 transition-transform group-hover:translate-x-0.5" />
                                        </Link>
                                    </div>
                                </article>
                            ))}
                        </div>
                    </section>
                )}
            </div>
        </>
    );
}
