# Salary Management System - Frontend

This is the Next.js 14 frontend for the Salary Management System, built with TypeScript and Tailwind CSS.

## Features

- **Landing Page**: Hero section with call-to-action buttons
- **User Registration**: Form for users to register and upload salary documents
- **Admin Panel**: Comprehensive salary management interface with sorting, filtering, and bulk operations
- **User Details**: Individual user information and salary history
- **Settings**: Account management and preferences
- **Responsive Design**: Mobile-first approach with Tailwind CSS

## Tech Stack

- **Framework**: Next.js 14 (App Router)
- **Language**: TypeScript
- **Styling**: Tailwind CSS
- **State Management**: React Hooks
- **API Client**: Custom fetch-based client

## Getting Started

### Prerequisites

- Node.js 18+ 
- npm or yarn

### Installation

1. Install dependencies:
```bash
npm install
```

2. Copy environment variables:
```bash
cp env.example .env.local
```

3. Update `.env.local` with your configuration:
```env
NEXT_PUBLIC_API_URL=http://localhost:8000/api
NEXT_PUBLIC_APP_NAME=SalaryPro
```

4. Run the development server:
```bash
npm run dev
```

5. Open [http://localhost:3000](http://localhost:3000) in your browser.

## Project Structure

```
src/
├── app/                    # Next.js App Router pages
│   ├── page.tsx          # Landing page
│   ├── register/         # User registration
│   ├── admin/            # Admin panel
│   ├── user/             # User details
│   └── settings/         # Settings page
├── components/            # Reusable UI components
│   ├── Header.tsx        # Navigation header
│   ├── Button.tsx        # Button component
│   ├── Input.tsx         # Input component
│   ├── FileUpload.tsx    # File upload component
│   ├── DataTable.tsx     # Data table component
│   └── Layout.tsx        # Page layout wrapper
├── hooks/                 # Custom React hooks
│   └── useForm.ts        # Form handling hook
└── lib/                   # Utility libraries
    └── api/              # API client and endpoints
        ├── client.ts     # Base API client
        └── salary.ts     # Salary API endpoints
```

## Components

### Header
Configurable navigation header with brand name, navigation items, and user profile.

### Button
Reusable button component with multiple variants (primary, secondary, outline) and sizes.

### Input
Form input component with label, error handling, and validation states.

### FileUpload
Drag-and-drop file upload component with file validation and error handling.

### DataTable
Sortable and paginated data table component for displaying salary records.

### Layout
Page wrapper component that provides consistent structure and navigation.

## API Integration

The frontend communicates with the Laravel backend through the API client:

- **Base URL**: Configurable via environment variables
- **Endpoints**: RESTful API for user and salary management
- **Authentication**: JWT-based authentication (to be implemented)
- **File Upload**: Multipart form data support

## Styling

- **Color Scheme**: Dark theme with blue accents (#0d80f2)
- **Typography**: Inter and Noto Sans fonts
- **Responsive**: Mobile-first design with container queries
- **Components**: Consistent design system with Tailwind utilities

## Development

### Available Scripts

- `npm run dev` - Start development server
- `npm run build` - Build for production
- `npm run start` - Start production server
- `npm run lint` - Run ESLint
- `npm run type-check` - Run TypeScript type checking

### Code Standards

- **Components**: PascalCase naming
- **Hooks**: camelCase naming
- **Files**: kebab-case naming
- **TypeScript**: Strict mode enabled
- **ESLint**: Next.js recommended configuration

## Testing

Testing setup with Jest and React Testing Library (to be implemented):

```bash
npm test
```

## Deployment

The application can be deployed to Vercel, Netlify, or any other hosting platform that supports Next.js.

## Contributing

1. Follow the established code standards
2. Use TypeScript for all new code
3. Ensure responsive design for all components
4. Add proper error handling and loading states
5. Update documentation as needed

## License

This project is part of the Salary Management System.
