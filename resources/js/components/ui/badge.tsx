import { Slot } from "@radix-ui/react-slot"
import { cva, type VariantProps } from "class-variance-authority"
import * as React from "react"

import { cn } from "@/lib/utils"

const badgeVariants = cva(
  "inline-flex items-center justify-center rounded-full border px-2.5 py-0.5 text-xs font-medium w-fit whitespace-nowrap shrink-0 [&>svg]:size-3 gap-1.5 [&>svg]:pointer-events-none focus-visible:border-ring focus-visible:ring-ring/50 focus-visible:ring-[3px] aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40 aria-invalid:border-destructive transition-colors overflow-hidden",
  {
    variants: {
      variant: {
        default:
          "border-transparent brand-gradient text-primary-foreground [a&]:hover:brightness-110",
        secondary:
          "border-border/60 bg-secondary text-secondary-foreground [a&]:hover:bg-secondary/80",
        destructive:
          "border-destructive/25 bg-destructive-soft text-destructive [a&]:hover:bg-destructive/15",
        outline:
          "border-border bg-transparent text-foreground [a&]:hover:bg-accent [a&]:hover:text-accent-foreground",
        success:
          "border-success/25 bg-success-soft text-success [a&]:hover:bg-success/15",
        warning:
          "border-warning/30 bg-warning-soft text-warning-foreground dark:text-warning [a&]:hover:bg-warning/20",
        info: "border-info/25 bg-info-soft text-info [a&]:hover:bg-info/15",
        muted:
          "border-border/70 bg-muted text-muted-foreground [a&]:hover:bg-muted/70",
      },
    },
    defaultVariants: {
      variant: "default",
    },
  }
)

function Badge({
  className,
  variant,
  asChild = false,
  ...props
}: React.ComponentProps<"span"> &
  VariantProps<typeof badgeVariants> & { asChild?: boolean }) {
  const Comp = asChild ? Slot : "span"

  return (
    <Comp
      data-slot="badge"
      className={cn(badgeVariants({ variant }), className)}
      {...props}
    />
  )
}

export { Badge, badgeVariants }
