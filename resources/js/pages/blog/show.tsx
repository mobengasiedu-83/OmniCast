import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, ArrowRight, Clock3, MessageSquareText } from 'lucide-react';
import { index as blogIndex } from '@/routes/blog';

type BlogPost = {
    title: string;
    slug: string;
    category: string | null;
    excerpt: string | null;
    body: string;
    meta_title: string | null;
    meta_description: string | null;
    published_at: string;
};

export default function BlogShow({ post }: { post: BlogPost }) {
    return (
        <>
            <Head title={post.meta_title || `${post.title} | Omnicast`}>
                {post.meta_description && (
                    <meta head-key="description" name="description" content={post.meta_description} />
                )}
                <meta head-key="og:type" property="og:type" content="article" />
                <meta head-key="og:title" property="og:title" content={post.meta_title || post.title} />
                {post.meta_description && (
                    <meta head-key="og:description" property="og:description" content={post.meta_description} />
                )}
            </Head>

            <main className="mx-auto max-w-5xl py-8 sm:py-12">
                <Link href={blogIndex()} className="inline-flex items-center gap-2 rounded-full border border-border/70 bg-card/70 px-4 py-2 text-sm text-muted-foreground transition-colors hover:border-brand/30 hover:text-foreground">
                    <ArrowLeft className="size-4" /> All articles
                </Link>
                <header className="relative isolate mt-6 overflow-hidden rounded-[2rem] border border-border/70 bg-card/75 px-6 py-9 shadow-sm sm:px-10 sm:py-12 lg:px-14 lg:py-14">
                    <div aria-hidden="true" className="pointer-events-none absolute inset-0 -z-10 mesh-backdrop opacity-40" />
                    <div aria-hidden="true" className="pointer-events-none absolute -top-20 -right-12 -z-10 size-72 rounded-full bg-brand/10 blur-3xl" />
                    <div className="max-w-4xl">
                        <span className="inline-flex items-center gap-2 rounded-full bg-brand-soft px-3 py-1 text-xs font-semibold text-accent-foreground ring-1 ring-brand/20">
                            <MessageSquareText className="size-3.5" /> {post.category || 'Omnicast insights'}
                        </span>
                        <h1 className="mt-6 text-3xl leading-[1.08] font-semibold tracking-tight text-balance sm:text-5xl lg:text-6xl">{post.title}</h1>
                        {post.excerpt && <p className="mt-5 max-w-3xl text-base leading-7 text-muted-foreground sm:text-lg sm:leading-8">{post.excerpt}</p>}
                        <div className="mt-7 inline-flex items-center gap-2 rounded-full border border-border/70 bg-background/75 px-3 py-2 text-xs text-muted-foreground shadow-sm">
                            <Clock3 className="size-3.5" />
                            <time dateTime={post.published_at}>{new Date(post.published_at).toLocaleDateString('en-GH', { dateStyle: 'long' })}</time>
                        </div>
                    </div>
                </header>
                <article
                    className="blog-article mx-auto mt-10 max-w-3xl px-1 text-[15px] leading-7 text-muted-foreground sm:mt-14 sm:text-base sm:leading-8"
                    dangerouslySetInnerHTML={{ __html: post.body }}
                />
                <div className="mx-auto mt-14 max-w-3xl border-t border-border/70 pt-7">
                    <Link href={blogIndex()} className="group inline-flex items-center gap-2 text-sm font-semibold text-foreground transition-colors hover:text-brand">
                        <ArrowLeft className="size-4 transition-transform group-hover:-translate-x-0.5" /> Back to all articles
                        <ArrowRight className="ml-1 size-4 opacity-0 transition-all group-hover:translate-x-0.5 group-hover:opacity-100" />
                    </Link>
                </div>
            </main>
        </>
    );
}
