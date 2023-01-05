import React, {Fragment, useEffect, useRef, useState} from 'react';
import { transformDateTime } from "../../../../utils/date";
import ReactPagination from "../../../../common/react-pagination/ReactPagination";


function CampaignUsersActivities(props) {
    const [activities, setActivities] = useState([]);

    const [usersActivitiesTablePagination, setUsersActivitiesTablePagination] = useState({});
    const [usersActivitiesCurrentPage, setUsersActivitiesCurrentPage] = useState(1);

    useEffect(() => {
        loadActivities(props.campaign.id,props.campaignUser.id);
    }, [props]);

    useEffect(() => {
        loadActivities(props.campaign.id,props.campaignUser.id);
    }, [usersActivitiesCurrentPage]);

    const loadActivities = async (campaign_id,user_id) => {
        let httpRes = await axiosFetch.get(route('policy-management.campaigns.get-users-activities',[campaign_id,user_id]),{
            params:{
                page :usersActivitiesCurrentPage,
                page_length: 10
            }
        });
        setUsersActivitiesTablePagination({
            links: httpRes.data.links,
            per_page: httpRes.data.per_page,
            total: httpRes.data.total,
        });
        let res = httpRes.data;
        if (res.data) {
            setActivities(res.data);
        }
    };

    return (
        <tr className="user-activities-tr">
          <td className="user-activities" colSpan="7">
            <div
              className={`px-2 pb-0 collapse ${
                props.activeKeys.includes(props.campaignUser.id) ? "show" : ""
              }`}
            >
              <h4 className="header-title my-3">Timeline for </h4>
              <ul className="list-group list-group-flush user-activity-lists">
                <li className="list-group-item user-activity-node d-flex align-items-center">
                    {
                       usersActivitiesCurrentPage == 1 && 
                       <Fragment>
                            <div className="node-icon node-icon-green">
                                <i className="dripicons-rocket"></i>
                            </div>
                            <span className="user-activity-node-title mx-2">
                                Campaign created
                            </span>
                            <span className="col-4 col-sm-4 col-md-3 col-lg-2">
                                {transformDateTime(props.campaign.created_at)}
                            </span>
                        </Fragment>
                    }
                </li>

                {activities && activities.map((activity) => {
                  return (
                    <li
                      key={activity.id}
                      className="list-group-item user-activity-node d-flex align-items-center"
                    >
                      {(() => {
                        switch (activity.type) {
                          case "email-sent":
                            return (
                              <div className="node-icon bg-success">
                                <i className="dripicons-mail"></i>
                              </div>
                            );
                          case "clicked-link":
                            return (
                              <div className="node-icon bg-primary">
                                <i className="ti-hand-point-up"></i>
                              </div>
                            );
                          case "email-sent-error":
                            return (
                              <div className="node-icon bg-danger">
                                <i className="dripicons-cross"></i>
                              </div>
                            );
                          case "policy-acknowledged":
                            return (
                              <div className="node-icon node-icon-green bg-warning">
                                <i className="fas fa-check"></i>
                              </div>
                            );

                          default:
                            return "";
                        }
                      })()}

                      <span className="user-activity-node-title mx-2">
                        {activity.activity}
                      </span>
                      <span className="col-4 col-sm-4 col-md-3 col-xl-2">
                        {transformDateTime(activity.created_at)}
                      </span>
                    </li>
                  );
                })}
                {usersActivitiesTablePagination.total > 10 &&
                  <ReactPagination
                      itemsCountPerPage={usersActivitiesTablePagination.per_page}
                      totalItemsCount={usersActivitiesTablePagination.total}
                      className="react-pagination pagination pagination-rounded justify-content"
                      onChange={(page) => {
                          setUsersActivitiesCurrentPage(page);
                      }}
                  ></ReactPagination>
                }
              </ul>
            </div>
          </td>
          
        </tr>
    );
}

export default CampaignUsersActivities;
