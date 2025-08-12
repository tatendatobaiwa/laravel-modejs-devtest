'use client';

import Layout from '@/components/Layout';

interface SalaryHistory {
  date: string;
  amount: string;
}

interface UpdateLog {
  action: string;
  date: string;
}

const salaryHistory: SalaryHistory[] = [
  { date: '2024-01-15', amount: '$85,000' },
  { date: '2023-07-01', amount: '$80,000' },
  { date: '2023-01-01', amount: '$75,000' }
];

const updateLog: UpdateLog[] = [
  { action: 'Salary updated to $85,000', date: '2024-01-15' },
  { action: 'Salary updated to $80,000', date: '2023-07-01' },
  { action: 'Salary updated to $75,000', date: '2023-01-01' }
];

export default function UserDetailsPage() {
  return (
    <Layout 
      brandName="PayTrack"
      navigationItems={[
        { href: '/dashboard', label: 'Dashboard' },
        { href: '/users', label: 'Users' },
        { href: '/reports', label: 'Reports' },
        { href: '/settings', label: 'Settings' }
      ]}
      showUserProfile={true}
    >
      <div className="flex flex-wrap justify-between gap-3 p-4">
        <div className="flex min-w-72 flex-col gap-3">
          <p className="text-white tracking-light text-[32px] font-bold leading-tight">User Details</p>
          <p className="text-[#9cabba] text-sm font-normal leading-normal">
            View and manage user information and salary history.
          </p>
        </div>
      </div>
      
      <h2 className="text-white text-[22px] font-bold leading-tight tracking-[-0.015em] px-4 pb-3 pt-5">
        Personal Information
      </h2>
      
      <div className="p-4 grid grid-cols-[20%_1fr] gap-x-6">
        <div className="col-span-2 grid grid-cols-subgrid border-t border-t-[#3b4754] py-5">
          <p className="text-[#9cabba] text-sm font-normal leading-normal">Name</p>
          <p className="text-white text-sm font-normal leading-normal">Sophia Carter</p>
        </div>
        <div className="col-span-2 grid grid-cols-subgrid border-t border-t-[#3b4754] py-5">
          <p className="text-[#9cabba] text-sm font-normal leading-normal">Email</p>
          <p className="text-white text-sm font-normal leading-normal">sophia.carter@email.com</p>
        </div>
      </div>
      
      <h2 className="text-white text-[22px] font-bold leading-tight tracking-[-0.015em] px-4 pb-3 pt-5">
        Salary History
      </h2>
      
      <div className="px-4 py-3">
        <div className="flex overflow-hidden rounded-lg border border-[#3b4754] bg-[#111418]">
          <table className="flex-1">
            <thead>
              <tr className="bg-[#1b2127]">
                <th className="px-4 py-3 text-left text-white w-[400px] text-sm font-medium leading-normal">
                  Date
                </th>
                <th className="px-4 py-3 text-left text-white w-[400px] text-sm font-medium leading-normal">
                  Amount
                </th>
                <th className="px-4 py-3 text-left text-white w-60 text-[#9cabba] text-sm font-medium leading-normal">
                  Document
                </th>
              </tr>
            </thead>
            <tbody>
              {salaryHistory.map((record, index) => (
                <tr key={index} className="border-t border-t-[#3b4754]">
                  <td className="h-[72px] px-4 py-2 w-[400px] text-[#9cabba] text-sm font-normal leading-normal">
                    {record.date}
                  </td>
                  <td className="h-[72px] px-4 py-2 w-[400px] text-[#9cabba] text-sm font-normal leading-normal">
                    {record.amount}
                  </td>
                  <td className="h-[72px] px-4 py-2 w-60 text-[#9cabba] text-sm font-bold leading-normal tracking-[0.015em]">
                    View
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
      
      <h2 className="text-white text-[22px] font-bold leading-tight tracking-[-0.015em] px-4 pb-3 pt-5">
        Update Log
      </h2>
      
      <div className="grid grid-cols-[40px_1fr] gap-x-2 px-4">
        {updateLog.map((log, index) => (
          <div key={index}>
            <div className="flex flex-col items-center gap-1 pt-3">
              <div className="text-white">
                <svg xmlns="http://www.w3.org/2000/svg" width="24px" height="24px" fill="currentColor" viewBox="0 0 256 256">
                  <path d="M227.31,73.37,182.63,28.68a16,16,0,0,0-22.63,0L36.69,152A15.86,15.86,0,0,0,32,163.31V208a16,16,0,0,0,16,16H92.69A15.86,15.86,0,0,0,104,219.31L227.31,96a16,16,0,0,0,0-22.63ZM92.69,208H48V163.31l88-88L180.69,120ZM192,108.68,147.31,64l24-24L216,84.68Z" />
                </svg>
              </div>
              {index < updateLog.length - 1 && (
                <div className="w-[1.5px] bg-[#3b4754] h-2 grow"></div>
              )}
            </div>
            <div className="flex flex-1 flex-col py-3">
              <p className="text-white text-base font-medium leading-normal">{log.action}</p>
              <p className="text-[#9cabba] text-base font-normal leading-normal">{log.date}</p>
            </div>
          </div>
        ))}
      </div>
    </Layout>
  );
}
