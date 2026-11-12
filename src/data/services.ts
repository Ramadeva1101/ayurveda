// Our Service pages. Text is the client's own (documents "AYURVEDIC CONSULTATION",
// "JAVANESE ENERGY HEALING" and "TRADITIONAL WATER PURIFICATION"), with
// "Nusantara" written as "Javanese" as on the rest of the site.

export type Block =
  | { type: 'steps'; title: string; intro?: string; items: string[] }
  | { type: 'list'; title: string; intro?: string; items: string[]; after?: string[] }
  | { type: 'cards'; title: string; items: { title: string; text: string }[] }
  | { type: 'chips'; title: string; items: string[] }
  | { type: 'text'; title: string; paragraphs: string[] }
  | { type: 'people'; title: string; items: { name: string; role: string }[] }
  // Without youtubeId the page shows a "Video coming soon" box until the clinic sends the video.
  | { type: 'video'; title: string; youtubeId?: string };

export type Service = {
  slug: string;
  eyebrow: string;
  title: string;
  subtitle: string;
  image: string;
  imageAlt: string;
  intro: string[];
  location?: string;
  blocks: Block[];
  notice?: { title: string; text: string };
  disclaimer?: string;
  cta: { title: string; text: string; bookLabel: string; bookService?: string; waText: string };
};

export const services: Service[] = [
  {
    slug: 'consultation',
    eyebrow: 'Ayurvedic Consultation',
    title: 'Personalised Guidance for Your Health &amp; Well-being',
    subtitle: 'Guided by Dr. Komang Sudarmi, BAMS., MS., PhD (Ayurveda)',
    image: '/images/consultation-hero.webp',
    imageAlt: 'Ayurvedic pulse reading during a consultation at Ayurvedic Wellness House',
    intro: [
      'At Ayurvedic Wellness House, we offer personalised Ayurvedic consultations to help you understand your individual constitution, identify current imbalances, and make informed choices for your well-being.',
      'Guided by Dr. Komang Sudarmi, BAMS., MS., PhD (Ayurveda), each consultation combines traditional Ayurvedic knowledge with a holistic understanding of individual health and lifestyle.',
      'Our approach brings together the ancient wisdom of Ayurveda and selected Javanese traditional healing practices to create a unique and personalised wellness experience.',
    ],
    location: 'Ayurveda Wellness House, Tabanan, Bali, Indonesia.',
    blocks: [
      {
        type: 'steps',
        title: 'What happens during your Ayurvedic consultation?',
        intro: 'A 60-minute individual consultation provides an opportunity to explore your health, understand your body, and receive practical recommendations based on Ayurvedic principles.',
        items: [
          'Understanding Your Constitution (Prakriti)',
          'Assessing Current Imbalances (Vikriti)',
          'Personalised Dietary Guidance (Ahara)',
          'Daily Routine &amp; Lifestyle (Dinacharya)',
          'Ayurvedic Treatment Recommendations (Chikitsa)',
          'Personalised Wellness Plan',
        ],
      },
      {
        type: 'steps',
        title: 'How does it work?',
        items: ['Initial Discussion', 'Ayurvedic Assessment', 'Personalised Recommendations', 'Follow-up Guidance'],
      },
      {
        type: 'cards',
        title: 'Consultation options',
        items: [
          { title: 'Full Ayurvedic Consultation', text: 'A comprehensive 60-minute individual session to understand your constitution, explore current health concerns, and receive personalised Ayurvedic recommendations.' },
          { title: 'Follow-up Consultation', text: 'A 30-minute review session to discuss your progress, address questions, and refine your existing wellness recommendations.' },
          { title: 'Online Ayurvedic Consultation', text: 'Receive personalised Ayurvedic guidance remotely, making professional consultation accessible wherever you are.' },
        ],
      },
      {
        type: 'list',
        title: 'How to prepare for your consultation',
        intro: 'To make your consultation more meaningful, we recommend preparing the following:',
        items: [
          'A brief history of your current health concerns and previous medical conditions.',
          'Recent medical reports or laboratory results, if available.',
          'A list of current medications, supplements, and herbal products.',
          'Information about your dietary habits, sleep patterns, digestion, and daily routine.',
          'Any specific questions or wellness goals you would like to discuss.',
        ],
        after: [
          'Please allow yourself sufficient time and choose a comfortable, quiet environment for your consultation.',
          'Please continue prescribed medication unless your treating healthcare professional advises otherwise.',
        ],
      },
      {
        type: 'text',
        title: 'Continuing your wellness journey',
        paragraphs: [
          'Ayurveda encourages us to understand health as an ongoing process of awareness, balance, and mindful living.',
          'Following your initial consultation, a follow-up session can help review your response to the recommendations, discuss any changes, and determine the next appropriate steps.',
          'Follow-up consultations may be arranged online or in person, according to your needs.',
        ],
      },
    ],
    notice: {
      title: 'Accommodation',
      text: 'Ayurveda Wellness House provides Ayurvedic consultations and wellness services. Accommodation, meals, yoga facilities, and retreat activities are arranged separately through Bali Silent Retreat.',
    },
    disclaimer: 'Ayurvedic consultations provide traditional wellness guidance and do not replace appropriate medical diagnosis, emergency care, or treatment by your healthcare professional.',
    cta: {
      title: 'Ready to begin?',
      text: 'Contact us via WhatsApp to enquire about availability and book your consultation.',
      bookLabel: 'Book your consultation',
      bookService: 'ayurvedic-one-on-one',
      waText: 'Hello, I would like to book an Ayurvedic consultation with Dr. Komang.',
    },
  },
  {
    slug: 'javanese-energy-healing',
    eyebrow: 'Javanese Energy Healing',
    title: 'Javanese Energy Healing',
    subtitle: 'Guided by Aji, our traditional Javanese healer, with Dr. Komang Sudarmi (Doc K)',
    image: '/images/javanese-energy-healing.webp',
    imageAlt: 'Javanese energy point-pressure therapy at Ayurvedic Wellness House',
    intro: [
      'At Ayurveda Wellness House, Javanese Energy Healing introduces traditional Javanese healing practices, focusing on the relationship between the body, its natural balance, and the flow of energy.',
      'Guided by Aji, our traditional Javanese healer, and accompanied by the professional guidance of Dr. Komang Sudarmi (Doc K), this experience combines traditional point-pressure techniques with Ayurvedic understanding of the body.',
    ],
    blocks: [
      {
        type: 'steps',
        title: 'What happens during your session?',
        items: [
          'Initial Ayurvedic Consultation (30 Minutes)',
          'Javanese Energy Point-Pressure Therapy by Aji (Healer) with Live Explanation by Dr. K (45 Minutes)',
          'Personalised Summary &amp; Recommendations &amp; QA (15 Minutes)',
        ],
      },
      {
        type: 'chips',
        title: 'Potential wellness benefits',
        items: ['Deep Relaxation', 'Relief from Physical Tension', 'Body Awareness', 'Mental Calmness', 'Holistic Well-being'],
      },
      { type: 'video', title: 'See a session' },
      {
        type: 'text',
        title: 'Follow-up therapy &amp; continued guidance',
        paragraphs: [
          'Well-being is an ongoing journey. Following your initial Javanese Energy Healing session, additional therapy sessions may be arranged according to your individual needs and response.',
          'Follow-up sessions focus on continuing traditional Javanese point-pressure therapy with Aji (Healer).',
        ],
      },
    ],
    cta: {
      title: 'Book your session',
      text: 'Contact us via WhatsApp to enquire about availability.',
      bookLabel: 'Book now',
      bookService: 'ayurvedic-javanese-healing-experience',
      waText: 'Hello, I would like to book a Javanese Energy Healing session.',
    },
  },
  {
    slug: 'water-purification',
    eyebrow: 'Traditional Water Purification',
    title: 'A Sacred Water Ritual in Jatiluwih',
    subtitle: 'Purify. Reconnect. Renew.',
    image: '/images/rice field surrounding_11zon.webp',
    imageAlt: 'Rice fields around Jatiluwih, Tabanan',
    intro: [
      'Experience a traditional water purification ritual at Pura Kedatuan Raksa Sidhi, Jatiluwih, using water from a natural, mineral-rich source.',
      'This guided experience brings together traditional purification practices, the natural qualities of sacred water, and the wisdom of traditional healing.',
      'Guided by Aji and Dr. Komang Sudarmi (Doc K), you will learn how to approach the ritual with awareness, intention, and mindfulness.',
      'More than a one-time experience, this is an opportunity to discover a meaningful purification practice that you can take home and continue to practise throughout your life.',
    ],
    location: 'Pura Kedatuan Raksa Sidhi, Jatiluwih, Bali, Indonesia.',
    blocks: [
      {
        type: 'steps',
        title: 'How does it work?',
        items: ['Introduction &amp; Guidance', 'Traditional Water Purification Ritual (30 Minutes)', 'Closing Guidance &amp; Take-Home Practice'],
      },
      { type: 'video', title: 'See the ritual' },
      {
        type: 'list',
        title: 'Before your ritual',
        intro: 'To ensure a comfortable and meaningful experience, we recommend:',
        items: [
          'Wear comfortable clothing suitable for a water purification ritual.',
          'A sarong will be provided for your use during the ritual.',
          'Bring a change of clothes and a towel.',
          "Follow the healer's instructions throughout the ritual.",
          'Arrive at the agreed time, as the ritual is conducted during a specific healing time.',
          'Come with an open mind and a respectful intention.',
        ],
      },
      {
        type: 'people',
        title: 'Guided by',
        items: [
          { name: 'Aji', role: 'Traditional Healer' },
          { name: 'Dr. Komang Sudarmi (Doc K)', role: 'Ayurvedic Physician' },
        ],
      },
    ],
    notice: {
      title: 'Important participation guideline',
      text: 'Women who are menstruating are not permitted to participate in the water purification ritual. Please plan your booking accordingly and inform the administration when making your reservation.',
    },
    cta: {
      title: 'Book your ritual',
      text: 'Follow-up: Additional purification rituals can be arranged.',
      bookLabel: 'Book via WhatsApp',
      waText: 'Hello, I would like to book the Traditional Water Purification ritual.',
    },
  },
];
