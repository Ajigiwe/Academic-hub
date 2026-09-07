import { z } from "zod";

export const registerSchema = z.object({
  email: z.string().email("Please enter a valid email address."),
  password: z
    .string()
    .min(8, "Password must be at least 8 characters.")
    .max(72, "Password must be at most 72 characters."),
  firstName: z.string().min(1, "First name is required.").max(60),
  lastName: z.string().min(1, "Last name is required.").max(60),
  phone: z
    .string()
    .regex(/^[0-9+\s-]{9,15}$/, "Please enter a valid phone number.")
    .optional()
    .or(z.literal("")),
});

export const loginSchema = z.object({
  email: z.string().email("Please enter a valid email address."),
  password: z.string().min(1, "Password is required."),
});

export const createOrderSchema = z.object({
  // Bundles are the unit of sale — one order item per bundle.
  bundleId: z.string().min(1, "Select a bundle to purchase."),
});

export type RegisterInput = z.infer<typeof registerSchema>;
export type LoginInput = z.infer<typeof loginSchema>;
