import { defineCollection, z } from 'astro:content';
import { glob } from 'astro/loaders';

const treatmentsCollection = defineCollection({
  loader: glob({ pattern: '**/*.md', base: './src/content/treatments' }),
  schema: z.object({
    name: z.string(),
    sanskritName: z.string().optional(),
    category: z.enum(['massage', 'dhara', 'basti', 'specialized', 'signature']),
    description: z.string(),
    duration: z.string().optional(),
    price: z.string().optional(),
    icon: z.string().default('lotus'),
    featured: z.boolean().default(false),
    benefits: z.array(z.string()).optional(),
    contraindications: z.array(z.string()).optional(),
  }),
});

export const collections = {
  treatments: treatmentsCollection,
};
