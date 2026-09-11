"use client";

/**
 * Submit button with a JS confirm() guard for destructive server-action
 * forms. Lives in a client component because server-rendered forms cannot
 * attach event handlers; it is safe to embed inside a server <form>.
 */
export function ConfirmSubmit({
  message,
  className,
  disabled,
  title,
  children,
}: {
  message: string;
  className?: string;
  disabled?: boolean;
  title?: string;
  children: React.ReactNode;
}) {
  return (
    <button
      type="submit"
      className={className}
      disabled={disabled}
      title={title}
      onClick={(e) => {
        if (!window.confirm(message)) e.preventDefault();
      }}
    >
      {children}
    </button>
  );
}
