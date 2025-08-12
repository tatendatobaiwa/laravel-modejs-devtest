import Layout from '@/components/Layout';
import Button from '@/components/Button';
import Link from 'next/link';

export default function LandingPage() {
  return (
    <Layout 
      brandName="SalaryPro"
      showAuthButtons={true}
    >
      <div className="@container">
        <div className="@[480px]:p-4">
          <div
            className="flex min-h-[480px] flex-col gap-6 bg-cover bg-center bg-no-repeat @[480px]:gap-8 @[480px]:rounded-lg items-start justify-end px-4 pb-10 @[480px]:px-10"
            style={{
              backgroundImage: 'linear-gradient(rgba(0, 0, 0, 0.1) 0%, rgba(0, 0, 0, 0.4) 100%), url("https://lh3.googleusercontent.com/aida-public/AB6AXuDOw5TMZ2tjK-MrJNGhFdMo14GUjx-jSO18iINy6nJ3G-c1KNu5yf4YT3C6P5HiwLERRuDv_uL6olZknPslHK8jUmyRw1wVwqcSloVQ4AHK6u0eDrJnVs6xu_VhRRYnCMxxQfmSsj0JchFde9A0wp1DPGic3W5qaWib6Mhq628T9AGzIKZneXNGq-kPZch7XKP2bL2_47XBL3F_eZpjMyArcojRq7GYR05vgO8VT2AOrXpXRY7pl6FnfI35jID1kYGkQ-B5BknLqqm3")'
            }}
          >
            <div className="flex flex-col gap-2 text-left">
              <h1 className="text-white text-4xl font-black leading-tight tracking-[-0.033em] @[480px]:text-5xl @[480px]:font-black @[480px]:leading-tight @[480px]:tracking-[-0.033em]">
                Streamline Your Salary Management
              </h1>
              <h2 className="text-white text-sm font-normal leading-normal @[480px]:text-base @[480px]:font-normal @[480px]:leading-normal">
                Effortlessly manage and track your salary details with our intuitive system. Register, upload your information, and gain insights into your earnings.
              </h2>
            </div>
            <div className="flex-wrap gap-3 flex">
              <Link href="/register">
                <Button size="lg">
                  Register
                </Button>
              </Link>
              <Link href="/admin">
                <Button variant="secondary" size="lg">
                  Admin Login
                </Button>
              </Link>
            </div>
          </div>
        </div>
      </div>
      
      <div className="flex flex-col gap-10 px-4 py-10 @container">
        <div className="flex flex-col gap-4">
          <h1 className="text-white tracking-light text-[32px] font-bold leading-tight @[480px]:text-4xl @[480px]:font-black @[480px]:leading-tight @[480px]:tracking-[-0.033em] max-w-[720px]">
            Key Features
          </h1>
          <p className="text-white text-base font-normal leading-normal max-w-[720px]">
            Our system offers a range of features designed to simplify salary management for both users and administrators.
          </p>
        </div>
        <div className="grid grid-cols-[repeat(auto-fit,minmax(158px,1fr))] gap-3 p-0">
          <div className="flex flex-1 gap-3 rounded-lg border border-[#3b4754] bg-[#1b2127] p-4 flex-col">
            <div className="text-white">
              <svg xmlns="http://www.w3.org/2000/svg" width="24px" height="24px" fill="currentColor" viewBox="0 0 256 256">
                <path d="M208,40H48A16,16,0,0,0,32,56v58.78c0,89.61,75.82,119.34,91,124.39a15.53,15.53,0,0,0,10,0c15.2-5.05,91-34.78,91-124.39V56A16,16,0,0,0,208,40Zm0,74.79c0,78.42-66.35,104.62-80,109.18-13.53-4.51-80-30.69-80-109.18V56H208ZM82.34,141.66a8,8,0,0,1,11.32-11.32L112,148.68l50.34-50.34a8,8,0,0,1,11.32,11.32l-56,56a8,8,0,0,1-11.32,0Z" />
              </svg>
            </div>
            <div className="flex flex-col gap-1">
              <h2 className="text-white text-base font-bold leading-tight">Secure Data Handling</h2>
              <p className="text-[#9cabba] text-sm font-normal leading-normal">
                Your salary information is protected with advanced security measures, ensuring confidentiality and peace of mind.
              </p>
            </div>
          </div>
          
          <div className="flex flex-1 gap-3 rounded-lg border border-[#3b4754] bg-[#1b2127] p-4 flex-col">
            <div className="text-white">
              <svg xmlns="http://www.w3.org/2000/svg" width="24px" height="24px" fill="currentColor" viewBox="0 0 256 256">
                <path d="M213.66,82.34l-56-56A8,8,0,0,0,152,24H56A16,16,0,0,0,40,40V216a16,16,0,0,0,16,16H200a16,16,0,0,0,16-16V88A8,8,0,0,0,213.66,82.34ZM160,51.31,188.69,80H160ZM200,216H56V40h88V88a8,8,0,0,0,8,8h48V216Z" />
              </svg>
            </div>
            <div className="flex flex-col gap-1">
              <h2 className="text-white text-base font-bold leading-tight">Easy Upload & Management</h2>
              <p className="text-[#9cabba] text-sm font-normal leading-normal">
                Quickly upload your salary details and manage them through a user-friendly interface, making updates and adjustments simple.
              </p>
            </div>
          </div>
          
          <div className="flex flex-1 gap-3 rounded-lg border border-[#3b4754] bg-[#1b2127] p-4 flex-col">
            <div className="text-white">
              <svg xmlns="http://www.w3.org/2000/svg" width="24px" height="24px" fill="currentColor" viewBox="0 0 256 256">
                <path d="M232,208a8,8,0,0,1-8,8H32a8,8,0,0,1-8-8V48a8,8,0,0,1,16,0v94.37L90.73,98a8,8,0,0,1,10.07-.38l58.81,44.11L218.73,90a8,8,0,1,1,10.54,12l-64,56a8,8,0,0,1-10.07.38L96.39,114.29,40,163.63V200H224A8,8,0,0,1,232,208Z" />
              </svg>
            </div>
            <div className="flex flex-col gap-1">
              <h2 className="text-white text-base font-bold leading-tight">Comprehensive Reporting</h2>
              <p className="text-[#9cabba] text-sm font-normal leading-normal">
                Access detailed reports and analytics on salary data, providing valuable insights for financial planning and decision-making.
              </p>
            </div>
          </div>
        </div>
      </div>
      
      <footer className="flex justify-center">
        <div className="flex max-w-[960px] flex-1 flex-col">
          <footer className="flex flex-col gap-6 px-5 py-10 text-center @container">
            <div className="flex flex-wrap items-center justify-center gap-6 @[480px]:flex-row @[480px]:justify-around">
              <a className="text-[#9cabba] text-base font-normal leading-normal min-w-40" href="#">Terms of Service</a>
              <a className="text-[#9cabba] text-base font-normal leading-normal min-w-40" href="#">Privacy Policy</a>
              <a className="text-[#9cabba] text-base font-normal leading-normal min-w-40" href="#">Contact Us</a>
            </div>
            <p className="text-[#9cabba] text-base font-normal leading-normal">© 2024 SalaryPro. All rights reserved.</p>
          </footer>
        </div>
      </footer>
    </Layout>
  );
}
